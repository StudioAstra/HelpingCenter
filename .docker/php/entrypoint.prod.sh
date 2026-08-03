#!/bin/sh
set -e

echo "⏳  Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "🗑  Clearing cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

chown -R www-data:www-data var

echo "✅  Ready"
exec "$@"
