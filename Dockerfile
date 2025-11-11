# syntax=docker/dockerfile:1

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/webroot

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libsqlite3-dev; \
    rm -rf /var/lib/apt/lists/*; \
    docker-php-ext-install pdo_sqlite; \
    a2enmod rewrite; \
    sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf; \
    sed -ri "s!/var/www/!${APACHE_DOCUMENT_ROOT}/!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf; \
    printf '<Directory %s>\n    AllowOverride All\n    Require all granted\n</Directory>\n' "$APACHE_DOCUMENT_ROOT" > /etc/apache2/conf-available/shortener.conf; \
    a2enconf shortener

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . ./

RUN set -eux; \
    mkdir -p data; \
    chown -R www-data:www-data data vendor src webroot; \
    chmod 750 data

ENV SHORTENER_SECRET=change-me

EXPOSE 80

CMD ["apache2-foreground"]
