# syntax=docker/dockerfile:1.7
# Fundly LOS API image (php-fpm). One image serves the api, worker and
# scheduler containers; only the command differs.
#
# Build from the repository root:
#   docker build -f deploy/docker/Dockerfile -t fundly-los-api:local .

ARG PHP_VERSION=8.3

# ---- dependencies --------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts \
        --prefer-dist --optimize-autoloader

# ---- runtime -------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-bookworm AS runtime

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev unzip; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl bcmath opcache zip pcntl; \
    pecl install redis-6.1.0; \
    docker-php-ext-enable redis; \
    # sodium (Ed25519 licences, XChaCha20 field encryption) is bundled with the official image
    php -m | grep -qi sodium; \
    apt-get purge -y --auto-remove libpq-dev libicu-dev libzip-dev; \
    apt-get install -y --no-install-recommends libpq5 libicu72 libzip4; \
    rm -rf /var/lib/apt/lists/* /tmp/pear

COPY deploy/docker/php/php.ini      /usr/local/etc/php/conf.d/zz-fundly.ini
COPY deploy/docker/php/www.conf     /usr/local/etc/php-fpm.d/zz-fundly.conf

WORKDIR /var/www/fundly
COPY --chown=www-data:www-data backend/ ./
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor

# No .env is baked in: configuration comes from the environment / env files,
# secrets from mounted files (/run/secrets). Caches are built at start-up
# by the entrypoint so they reflect the installation's configuration.
RUN set -eux; \
    rm -f .env; \
    rm -rf tests storage/framework/testing phpunit.xml phpstan.neon; \
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/audit-checkpoints bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    php artisan package:discover --ansi

COPY --chmod=0755 deploy/docker/php/entrypoint.sh /usr/local/bin/fundly-entrypoint

USER www-data
EXPOSE 9000
ENTRYPOINT ["fundly-entrypoint"]
CMD ["php-fpm", "--nodaemonize"]
