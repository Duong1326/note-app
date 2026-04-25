#!/bin/bash
set -e

echo "=== Render.com Start ==="

# Cache cấu hình cho production
echo ">> Caching configuration..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Chạy migrate tự động khi deploy
echo ">> Running migrations..."
php artisan migrate --force || true

# Khởi động Apache
echo ">> Starting Apache..."
exec "$@"
