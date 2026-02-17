#!/bin/sh
set -e

echo "==> Installing dependencies..."
composer install --no-interaction --optimize-autoloader

echo "==> Waiting for database..."
until php bin/console doctrine:database:create --if-not-exists --no-interaction 2>/dev/null; do
    echo "    Database not ready, retrying in 2s..."
    sleep 2
done

echo "==> Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>/dev/null || \
    echo "    No migrations to run (generate them with: docker compose exec php bin/console make:migration)"

echo "==> Building frontend assets..."
if [ ! -f public/build/entrypoints.json ]; then
    npm install --no-audit --no-fund
    npm run build
else
    echo "    Assets already built, skipping."
fi

echo "==> Clearing cache..."
php bin/console cache:clear --no-interaction

echo "==> Setting permissions..."
chown -R www-data:www-data var/ 2>/dev/null || true

echo "==> Ready! Starting PHP-FPM..."
exec php-fpm
