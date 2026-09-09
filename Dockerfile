FROM composer:2.8 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts \
    --ignore-platform-req=ext-bcmath --ignore-platform-req=ext-gd
COPY think ./think
COPY app ./app
COPY config ./config
RUN php think service:discover

FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl libcurl4-openssl-dev libfreetype6-dev libjpeg62-turbo-dev \
        libonig-dev libpng-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath curl gd mbstring opcache pdo_mysql \
    && a2enmod headers rewrite \
    && apt-get clean \
    && find /var/lib/apt/lists -mindepth 1 -delete

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
WORKDIR /var/www/html

COPY . .
COPY --from=dependencies /app/vendor ./vendor
COPY docker/apache-vmq.conf /etc/apache2/conf-available/zzz-vmq.conf
COPY docker/php-vmq.ini /usr/local/etc/php/conf.d/vmq.ini

RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && a2enconf zzz-vmq \
    && chmod +x /var/www/html/docker/entrypoint.sh \
    && mkdir -p /var/www/html/runtime \
    && chown -R www-data:www-data /var/www/html/runtime

EXPOSE 80
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]
