#!/usr/bin/env bash
# ============================================================================
#  CicleVibes - Entry point de producción (Render / Docker)
#  Ajusta el puerto dinámico de Render, permisos, cachés de Laravel y
#  migraciones NO destructivas, y finalmente arranca Apache.
# ============================================================================
set -euo pipefail

cd /var/www/html

echo "[entrypoint] Iniciando CicleVibes en producción..."

# ---------------------------------------------------------------------------
# 1) Puerto dinámico de Render (variable de entorno PORT).
#    Apache está configurado para escuchar en 8080 como plantilla; aquí se
#    reemplaza por el puerto real que Render asigna ($PORT).
# ---------------------------------------------------------------------------
PORT="${PORT:-8080}"
echo "[entrypoint] Configurando Apache para escuchar en 0.0.0.0:${PORT}"

if [ -f /etc/apache2/ports.conf ]; then
    sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
fi

if [ -f /etc/apache2/sites-available/000-default.conf ]; then
    sed -i "s/:8080>/${PORT}>/" /etc/apache2/sites-available/000-default.conf || true
fi

# ---------------------------------------------------------------------------
# 2) Permisos y estructura de storage/ y bootstrap/cache.
# ---------------------------------------------------------------------------
mkdir -p storage/app \
         storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/testing \
         storage/logs

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# ---------------------------------------------------------------------------
# 3) Cachés de Laravel (solo si hay clave de aplicación definida y el
#    directorio bootstrap/cache es escribible). Se usa '|| echo' para que un
#    fallo puntual (p. ej. route:cache con cierres) no aborte el arranque.
# ---------------------------------------------------------------------------
if [ -n "${APP_KEY:-}" ]; then
    echo "[entrypoint] Generando cachés de Laravel..."
    php artisan config:cache --no-interaction 2>/dev/null || echo "[entrypoint] config:cache omitido"
    php artisan route:cache --no-interaction 2>/dev/null || echo "[entrypoint] route:cache omitido"
    php artisan view:cache --no-interaction 2>/dev/null || echo "[entrypoint] view:cache omitido"
else
    echo "[entrypoint] APP_KEY no definido; omitiendo cachés de Laravel. Define APP_KEY en Render."
fi

# ---------------------------------------------------------------------------
# 4) Migraciones NO destructivas. Solo aplica migraciones pendientes, nunca
#    borra datos. Si la BD no está lista aún, no detiene el arranque.
# ---------------------------------------------------------------------------
if [ -n "${APP_KEY:-}" ]; then
    echo "[entrypoint] Ejecutando migraciones (--force, no destructivas)..."
    php artisan migrate --force --no-interaction 2>/dev/null \
        || echo "[entrypoint] Migrate falló o la BD no está disponible; continúa el arranque."
fi

# ---------------------------------------------------------------------------
# 5) Arranca Apache en primer plano (proceso principal del contenedor).
# ---------------------------------------------------------------------------
echo "[entrypoint] Arrancando Apache (apache2-foreground)..."
exec apache2-foreground
