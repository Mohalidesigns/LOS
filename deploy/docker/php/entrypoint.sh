#!/bin/sh
# Builds Laravel's config/route/event caches from the runtime environment,
# then execs the container command (php-fpm, queue:work or schedule:work).
set -eu
cd /var/www/fundly

if [ -z "${APP_KEY:-}" ] && [ -n "${APP_KEY_FILE:-}" ] && [ -r "${APP_KEY_FILE}" ]; then
    APP_KEY="$(cat "${APP_KEY_FILE}")"; export APP_KEY
fi
if [ -z "${DB_PASSWORD:-}" ] && [ -n "${DB_PASSWORD_FILE:-}" ] && [ -r "${DB_PASSWORD_FILE}" ]; then
    DB_PASSWORD="$(cat "${DB_PASSWORD_FILE}")"; export DB_PASSWORD
fi
if [ -z "${DB_OWNER_PASSWORD:-}" ] && [ -n "${DB_OWNER_PASSWORD_FILE:-}" ] && [ -r "${DB_OWNER_PASSWORD_FILE}" ]; then
    DB_OWNER_PASSWORD="$(cat "${DB_OWNER_PASSWORD_FILE}")"; export DB_OWNER_PASSWORD
fi
if [ -z "${REDIS_PASSWORD:-}" ] && [ -n "${REDIS_PASSWORD_FILE:-}" ] && [ -r "${REDIS_PASSWORD_FILE}" ]; then
    REDIS_PASSWORD="$(cat "${REDIS_PASSWORD_FILE}")"; export REDIS_PASSWORD
fi

# storage/framework and bootstrap/cache are tmpfs on a read-only root filesystem.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache

php artisan config:cache --no-ansi >/dev/null
php artisan route:cache --no-ansi >/dev/null
php artisan event:cache --no-ansi >/dev/null

exec "$@"
