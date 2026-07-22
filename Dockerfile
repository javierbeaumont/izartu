# Runtime image: dependency-free.
FROM php:8.5-apache AS runtime

RUN docker-php-ext-install pdo pdo_mysql

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite

# Test image: runtime + Composer, for the dev/test toolchain only. Never shipped.
FROM runtime AS test

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
