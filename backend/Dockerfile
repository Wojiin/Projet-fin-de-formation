FROM php:8.4-fpm-alpine

RUN apk add --no-cache icu-dev libzip-dev \
    && docker-php-ext-install intl opcache pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts

COPY . .
RUN composer run-script post-install-cmd \
    && chown -R www-data:www-data var

EXPOSE 9000

CMD ["php-fpm"]
