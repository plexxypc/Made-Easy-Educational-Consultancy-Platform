# syntax=docker/dockerfile:1
#
# Multi-stage build for the Made Easy platform.
#   target "app"   -> PHP-FPM image used by the app and worker services
#   target "caddy" -> Caddy image carrying a copy of public/ for static files

# ---------------------------------------------------------------------------
# PHP base: runtime + extensions + composer
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS php_base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql intl zip bcmath opcache pcntl exif \
    && apk add --no-cache fcgi

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# Composer dependencies (cached separately from app code)
# ---------------------------------------------------------------------------
FROM php_base AS vendor

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# ---------------------------------------------------------------------------
# Front-end assets (Vite + Tailwind)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN npm run build

# ---------------------------------------------------------------------------
# Application image (php-fpm) — used by "app" and "worker"
# ---------------------------------------------------------------------------
FROM php_base AS app

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=assets /var/www/html/public/build ./public/build

# Autoload dump runs package:discover and filament:upgrade (publishes Filament
# assets into public/). The array/sync drivers keep it from touching the DB.
RUN CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync \
        composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/app/private storage/app/public storage/fonts storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && sed -i 's/\r$//' docker/php/entrypoint.sh \
    && install -m 0755 docker/php/entrypoint.sh /usr/local/bin/app-entrypoint

USER www-data

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Caddy with the public/ directory baked in (static files served directly)
# ---------------------------------------------------------------------------
FROM caddy:2-alpine AS caddy

COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=app /var/www/html/public /var/www/html/public
