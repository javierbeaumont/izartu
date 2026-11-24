FROM composer:2@sha256:cd3a90dc5624c59a2f9ddf80dad08569f06416db31de519024f249d63bca554b AS composer

FROM php:8.3-cli@sha256:f1ff1ba29b90550ca0b69aa6d97ed2bcf40fb109fbf2ecad9817dad524a816e8

RUN docker-php-ext-install pdo_mysql

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git \
      jq \
      unzip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
