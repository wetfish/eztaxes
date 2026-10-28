# Builds EzTaxes into images, so a server runs only code built from one
# commit and never mounts it from the repository. Two images:
#
#   php    PHP-FPM with the application, its Composer packages, and the
#          built assets; the php and migrate services run it
#   nginx  nginx with the public directory and the built assets
#
# The Laravel application is in laravel/, at /var/www/laravel in both.

# --- PHP with the extensions EzTaxes uses ------------------------------------

FROM php:8.5.12-fpm AS php-base

# mbstring is built into the official image; zip needs libzip
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql bcmath zip

COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini
COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/laravel

# --- Composer packages -------------------------------------------------------

FROM php-base AS vendor

ENV COMPOSER_ALLOW_SUPERUSER=1

# The lock file first, so Docker's cache skips the download until it changes
COPY laravel/composer.json laravel/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# storage/ is left out of the build, so its directories are created here.
# Dumping the autoloader also runs package:discover, which boots Laravel.
COPY laravel/ ./
RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && composer dump-autoload --no-dev --optimize --no-interaction

# --- Built CSS and JavaScript ------------------------------------------------

FROM node:24.22.2-alpine AS assets

WORKDIR /var/www/laravel

COPY laravel/package.json laravel/package-lock.json ./
RUN npm ci --no-audit --no-fund

# Tailwind scans the views for class names, including Laravel's pagination
# views, which come with the Composer packages
COPY laravel/ ./
COPY --from=vendor /var/www/laravel/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
    vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN mkdir -p storage/framework/views && npm run build

# --- PHP-FPM, for the php and migrate services -------------------------------

FROM php-base AS php

COPY --from=vendor /var/www/laravel ./
COPY --from=assets /var/www/laravel/public/build public/build
COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/eztaxes-entrypoint

# The code stays root's; PHP writes only caches, compiled views, and sessions
RUN chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["eztaxes-entrypoint"]
CMD ["php-fpm"]

# --- nginx, for the nginx service --------------------------------------------

FROM nginx:1.30.6-alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY laravel/public /var/www/laravel/public
COPY --from=assets /var/www/laravel/public/build /var/www/laravel/public/build
