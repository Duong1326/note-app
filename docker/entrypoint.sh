#!/bin/bash
set -e

cd /var/www

# -----------------------------------------------
# Fix slow I/O on Windows Docker volume mounts
# -----------------------------------------------
export COMPOSER_PROCESS_TIMEOUT=900
export COMPOSER_NO_INTERACTION=1

# Install PHP dependencies if vendor is empty
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies (this may take a few minutes on Windows)..."
    composer install --prefer-dist --optimize-autoloader --no-interaction
fi

# Generate APP_KEY if not set
if [ -f ".env" ] && grep -q "^APP_KEY=$" .env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Set permissions
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# Wait for MySQL to be ready
echo "Waiting for MySQL..."
max_tries=30
count=0
until php -r "
    try {
        new PDO(
            'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        echo 'OK';
    } catch (Exception \$e) {
        exit(1);
    }
" 2>/dev/null; do
    count=$((count + 1))
    if [ $count -ge $max_tries ]; then
        echo "MySQL not reachable after ${max_tries} attempts. Starting anyway..."
        break
    fi
    echo "  Attempt $count/$max_tries - MySQL not ready yet..."
    sleep 2
done
echo "MySQL is ready!"

# Run migrations
echo "Running migrations..."
php artisan migrate --force 2>/dev/null || echo "Migration failed or already up to date"

# Cache config for faster startup
echo "Optimizing Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Application is ready! Visit http://localhost:8080"

# Start queue worker in background
echo "Starting queue worker..."
php artisan queue:work --tries=3 --sleep=3 --daemon &

# Start PHP-FPM
exec php-fpm
