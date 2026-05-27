FROM composer:2 AS composer

FROM php:latest AS base

RUN apt-get update -qq && apt-get install -y --no-install-recommends \
    git libicu-dev libonig-dev libzip-dev zip unzip \
    && docker-php-ext-install pdo_mysql intl mbstring zip \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY conf.d/error_reporting.ini /usr/local/etc/php/conf.d/error_reporting.ini
COPY conf.d/upload.ini /usr/local/etc/php/conf.d/upload.ini

COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/app

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
