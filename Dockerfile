
# ============================================================================
# CicleVibes - Despliegue en Render (Docker)
# Laravel 12 / PHP 8.2 / MySQL externo / Vite + Leaflet
# ============================================================================

# Etapa 1: construir assets de producción con Node + Vite
FROM node:22-alpine AS assets

WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# Etapa 2: PHP + Apache
FROM php:8.2-apache AS runtime

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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php-prod.ini /usr/local/etc/php/conf.d/99-prod.ini

WORKDIR /var/www/html

COPY . .

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --prefer-dist

COPY --from=assets /build/public/build ./public/build

# Preparar directorios y permisos de Laravel
RUN set -eux; \
    mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        storage/logs \
        bootstrap/cache; \
    touch storage/logs/laravel.log; \
    chown -R www-data:www-data storage bootstrap/cache; \
    find storage bootstrap/cache -type d -exec chmod 775 {} \;; \
    find storage bootstrap/cache -type f -exec chmod 664 {} \;

# Configuración de Apache
COPY docker/laravel.conf /etc/apache2/sites-available/000-default.conf

RUN printf "ServerName localhost\n" >> /etc/apache2/apache2.conf

EXPOSE 8080

ENTRYPOINT ["bash", "/var/www/html/docker-entrypoint.sh"]
