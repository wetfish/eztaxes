#!/bin/sh
#
# Starts the php and migrate containers. Laravel's caches hold the
# configuration from laravel/.env, which only exists when the container
# runs, so they are built here rather than in the image. Local development
# skips them, so code changes show without clearing caches.

set -e

if [ "$APP_ENV" != "local" ]; then
    php artisan optimize
    chown -R www-data:www-data storage bootstrap/cache
fi

exec docker-php-entrypoint "$@"
