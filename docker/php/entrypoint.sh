#!/bin/sh
# Container entrypoint shared by the "app" (php-fpm) and "worker" services.
#
# Caches config/routes/views at start-up (not at image build time) so the
# cached config reflects the runtime environment variables from .env.
set -e

cd /var/www/html

if [ "${APP_ENV}" = "production" ]; then
    php artisan optimize
    php artisan filament:optimize
else
    # Not optimize:clear: its cache:clear hits the database cache table, which
    # does not exist until migrations have run.
    php artisan config:clear > /dev/null
    php artisan route:clear > /dev/null
    php artisan view:clear > /dev/null
fi

exec "$@"
