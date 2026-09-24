#!/bin/sh

echo "==> entrypoint.sh start (APP_ENV=${APP_ENV:-dev})"

if [ "${APP_ENV:-dev}" != "prod" ]; then
    echo "==> Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader

    echo "==> Building frontend assets..."
    if [ ! -f public/build/entrypoints.json ]; then
        npm install --no-audit --no-fund
        npm run build
    else
        echo "    Assets already built, skipping."
    fi
fi

echo "==> Waiting for database..."
until php -r "
\$url = getenv('DATABASE_URL');
preg_match('#mysql://[^:@]+:[^@]*@([^:/]+)(?::(\d+))?/#', \$url, \$m);
\$fp = @fsockopen(\$m[1] ?? '', \$m[2] ?? 3306, \$e, \$es, 3);
if (!\$fp) exit(1);
fclose(\$fp);
" 2>/dev/null; do
    echo "    Database not ready, retrying in 2s..."
    sleep 2
done
echo "    Database ready."

echo "==> Running migrations..."
if php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1; then
    echo "    Migrations OK."
else
    echo "!!! ERROR: migrations failed (see above) !!!"
fi

echo "==> Clearing cache..."
if php bin/console cache:clear --no-warmup --no-debug 2>&1; then
    echo "    Cache cleared."
else
    echo "!!! ERROR: cache:clear failed (see above) !!!"
fi

echo "==> Setting permissions..."
chown -R www-data:www-data var/ 2>/dev/null || true

echo "==> Starting PHP-FPM..."
exec "$@"
