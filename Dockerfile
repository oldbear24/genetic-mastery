# syntax=docker/dockerfile:1

# --- Frontend assets ---------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# --- PHP dependencies --------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# --- Runtime: nginx + PHP-FPM (supervisord), scheduler via a second container --
FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache nginx supervisor sqlite tini su-exec \
        libpng libjpeg-turbo libwebp freetype icu-libs libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev icu-dev postgresql-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd intl pdo_pgsql opcache pcntl \
    && apk del .build-deps \
    && php -m | grep -E '^(sodium|pdo_sqlite|pdo_pgsql|gd|fileinfo)$'

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-genetic.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-genetic.conf
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

RUN chmod +x /usr/local/bin/entrypoint \
    && rm -rf tests design docs/*.png node_modules \
    && mkdir -p storage/database storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=json \
    DB_DATABASE=/var/www/html/storage/database/database.sqlite

EXPOSE 80
VOLUME ["/var/www/html/storage"]

ENTRYPOINT ["/sbin/tini", "--", "entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
