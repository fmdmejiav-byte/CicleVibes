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
#    Apache debe escuchar en 0.0.0.0:$PORT. Se usa el mecanismo oficial del
#    contenedor (APACHE_HTTP_PORT) para que Apache no lo reinicie, y se genera
#    de forma autoritativa el vhost de Laravel apuntando a public/.
# ---------------------------------------------------------------------------
PORT="${PORT:-8080}"
export APACHE_HTTP_PORT="$PORT"
echo "[entrypoint] Apache escuchando en 0.0.0.0:${PORT} (APACHE_HTTP_PORT=${APACHE_HTTP_PORT})"

# Genera el vhost de Laravel con el puerto resuelto, DocumentRoot correcto y
# acceso permitido. Esto evita el 403 por DocumentRoot incorrecto y el
# desajuste de puerto al reiniciar Apache.
cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:${PORT}>
    ServerAdmin webmaster@localhost
    ServerName localhost

    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Listen con el puerto resuelto (y asegura que el vhost esté habilitado).
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
a2ensite 000-default.conf >/dev/null 2>&1 || true

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

# Permisos del DocumentRoot para que Apache (www-data) pueda leer y ejecutar
# el front controller (/var/www/html/public/index.php) y el resto del código.
chown -R www-data:www-data /var/www/html 2>/dev/null || true
chmod -R u+rwX,go+rX /var/www/html 2>/dev/null || true

# ---------------------------------------------------------------------------
# 2bis) Certificado CA de MySQL (Aiven) con TLS/SSL, sin exponerlo en el repo.
#   Render inyecta el contenido del ca.pem en la variable SECRETA MYSQL_SSL_CA
#   (PEM multilínea). Aquí se materializa en un fichero temporal y se expone
#   la ruta a Laravel/PDO mediante MYSQL_ATTR_SSL_CA.
# ---------------------------------------------------------------------------
if [ -n "${MYSQL_SSL_CA:-}" ]; then
    CA_FILE="/tmp/ciclevibes-mysql-ca.pem"
    printf '%s\n' "$MYSQL_SSL_CA" > "$CA_FILE"
    chown www-data:www-data "$CA_FILE"
    chmod 644 "$CA_FILE"
    export MYSQL_ATTR_SSL_CA="$CA_FILE"
    # Más seguro: verifica el certificado del servidor contra esta CA.
    export MYSQL_ATTR_SSL_VERIFY_SERVER_CERT="${MYSQL_ATTR_SSL_VERIFY_SERVER_CERT:-true}"
    echo "[entrypoint] Certificado CA de MySQL escrito en ${CA_FILE} (MYSQL_ATTR_SSL_CA activo)."
else
    echo "[entrypoint] MYSQL_SSL_CA no definido; conexión MySQL sin CA SSL explícita (p. ej. local)."
fi

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
