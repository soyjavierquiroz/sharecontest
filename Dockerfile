FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev libonig-dev libxml2-dev libcurl4-openssl-dev \
    && docker-php-ext-install -j2 pdo_pgsql pgsql curl mbstring xml zip opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN rm -f bootstrap/cache/*.php \
    && chown -R www-data:www-data storage bootstrap/cache \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
EXPOSE 80
CMD ["apache2-foreground"]
