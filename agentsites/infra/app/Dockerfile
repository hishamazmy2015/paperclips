# PHP-FPM image shared by app / horizon / scheduler (spec §5). The source tree is
# bind-mounted at /srv/platform/app; `composer install` runs inside the container
# (infra/scripts/bootstrap.sh, deploy.sh), so the image holds only the runtime.
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-fpm-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git unzip libfcgi-bin \
        libpq-dev libzip-dev libicu-dev \
        libpng-dev libjpeg62-turbo-dev libwebp-dev libavif-dev libfreetype6-dev; \
    docker-php-ext-configure gd --with-jpeg --with-webp --with-avif --with-freetype; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl zip bcmath pcntl opcache gd exif; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY infra/app/php.ini /usr/local/etc/php/conf.d/zz-platform.ini
COPY infra/app/php-fpm.d/zz-platform.conf /usr/local/etc/php-fpm.d/zz-platform.conf

WORKDIR /srv/platform/app
