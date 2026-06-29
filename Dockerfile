# SIMA — Laravel 11 API (PHP 8.2 FPM)
# Target dev:  docker compose build app
# Target prod: docker build --target production -t sima-api .

FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        mysql-client \
        bash \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        bcmath \
        zip \
        intl \
        opcache \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ------------------------------------------------------------------ dev target
FROM base AS dev

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
RUN sed -i 's/opcache.validate_timestamps=0/opcache.validate_timestamps=1/' /usr/local/etc/php/conf.d/opcache.ini

# Dependencies installed at runtime via compose command for faster rebuilds.

# -------------------------------------------------------------- production target
FROM base AS production

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
