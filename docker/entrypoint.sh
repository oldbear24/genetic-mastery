#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/database storage/app/private storage/framework/cache storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
fi

chown -R www-data:www-data storage bootstrap/cache

# Only the web container migrates; the scheduler sets RUN_MIGRATIONS=false.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    su-exec www-data php artisan migrate --force
    su-exec www-data php artisan db:seed --force
fi

su-exec www-data php artisan config:cache
su-exec www-data php artisan route:cache
su-exec www-data php artisan view:cache
su-exec www-data php artisan event:cache

exec "$@"
