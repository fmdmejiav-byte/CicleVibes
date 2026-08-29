# ============================================================================
#  CicleVibes - Despliegue en Render (Docker)
#  Laravel 12 / PHP 8.2 / MySQL externo / Vite + Leaflet
# ============================================================================

# ---------------------------------------------------------------------------
#  Etapa 1: construir los assets de producción con Node + Vite
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /build

# Instala dependencias npm a partir del lockfile (fuente de verdad).
COPY package.json package-lock.json ./
RUN npm ci

# Compila los assets de Vite (incluye app.css con Leaflet, app.js, ruta-planner.js).
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------------------------------------------------------------------------
#  Etapa 2: imagen de ejecución (PHP + Apache)
# ---------------------------------------------------------------------------
FROM php:8.2-apache AS runtime

# Dependencias del sistema necesarias para compilar extensiones PHP.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libgmp-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        zip \
        intl \
        gmp \
        opcache \
        exif \
        pcntl \
        ctype \
        fileinfo; \
    a2enmod rewrite headers; \
    rm -rf /var/lib/apt/lists/*

# Binario oficial de Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Ajustes de PHP para producción.
COPY docker/php-prod.ini /usr/local/etc/php/conf.d/99-prod.ini

WORKDIR /var/www/html

# Copia el código de la aplicación (respeta .dockerignore: sin .env, vendor,
# node_modules, logs, etc.) antes de composer para que los post-scripts
# (package:discover) funcionen.
COPY . .

# Instala dependencias de Composer (sin paquetes de desarrollo).
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist

# Assets compilados en la etapa frontend.
COPY --from=assets /build/public/build ./public/build

# Estructura y permisos de storage/ y bootstrap/cache.
RUN mkdir -p \
        storage/app \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        storage/logs; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R 775 storage bootstrap/cache

# Apache: sirve public/ como DocumentRoot y Puerto servido por Render.
COPY docker/laravel.conf /etc/apache2/sites-available/000-default.conf
RUN printf "ServerName localhost\n" >> /etc/apache2/apache2.conf

# Render inyecta $PORT en tiempo de ejecución; el entry point ajusta el Listen.
EXPOSE 8080

# Entry point: ajusta el puerto, permisos, cachés y migraciones (no destructivas).
ENTRYPOINT ["bash", "/var/www/html/docker-entrypoint.sh"]
