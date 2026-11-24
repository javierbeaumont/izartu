FROM composer:2@sha256:cd3a90dc5624c59a2f9ddf80dad08569f06416db31de519024f249d63bca554b AS composer

FROM php:8.2-cli@sha256:080193293786a2d9442806eca60635516120a30309b694c4292a56a4f8e2d353

RUN docker-php-ext-install pdo_mysql

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git \
      jq \
      unzip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
