# Imagem de produção (servidor openclaw, ARM64): PHP 8.5 + Apache.
# Os assets (public/build) são compilados antes, na máquina de quem publica.
FROM php:8.5-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev default-mysql-client unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" intl zip gd bcmath exif pdo_mysql \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-fa.ini

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && rm -f public/hot \
    && chown -R www-data:www-data storage bootstrap/cache
