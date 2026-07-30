# Runtime image: dependency-free. PHP_VERSION lets CI build the same image on
# every supported PHP (see the README's supported-versions policy).
ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-apache AS runtime

RUN docker-php-ext-install pdo pdo_mysql

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite

# Test image: runtime + Composer, for the dev/test toolchain only. Never shipped.
FROM runtime AS test

# Pinned apt versions break as soon as Debian's archive moves on, and this image
# is dev-only, so the churn is not worth it.
# hadolint ignore=DL3008
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
