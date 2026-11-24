FROM composer:2@sha256:cd3a90dc5624c59a2f9ddf80dad08569f06416db31de519024f249d63bca554b AS composer

FROM php:8.4-cli@sha256:d9375942df6f191e43650a7ea7274e775f9a4526f9995ce90676f5330b5f51e6

RUN docker-php-ext-install pdo_mysql

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git \
      jq \
      unzip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
