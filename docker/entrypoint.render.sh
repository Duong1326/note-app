#!/bin/bash
set -e

echo "=== Render.com Start ==="

# Cấu hình Apache port động (Render cung cấp biến $PORT)
LISTEN_PORT="${PORT:-10000}"
echo ">> Configuring Apache to listen on port ${LISTEN_PORT}..."
sed -i "s/Listen 80/Listen ${LISTEN_PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${LISTEN_PORT}/g" /etc/apache2/sites-available/000-default.conf

# Cache cấu hình cho production
echo ">> Caching configuration..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Chạy migrate tự động khi deploy
echo ">> Running migrations..."
php artisan migrate --force || true

# Khởi động Apache
echo ">> Starting Apache on port ${LISTEN_PORT}..."
exec "$@"
