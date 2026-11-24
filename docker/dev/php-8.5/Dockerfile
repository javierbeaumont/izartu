FROM composer:2@sha256:cd3a90dc5624c59a2f9ddf80dad08569f06416db31de519024f249d63bca554b AS composer

FROM php:8.5-cli@sha256:dbf32b33595283b79fd1a37ebc1614bdf1b83d3750daad9a57d67dbb9b96ec16

RUN docker-php-ext-install pdo_mysql

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git \
      jq \
      unzip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

# composer-require-checker needs PHP 8.4: as a dev dependency of the app, `composer install` would fail on 8.2 and 8.3.
COPY composer.json composer.lock /opt/composer-require-checker/
# It runs without bcmath and intl, required by one of its libraries.
RUN composer install -d /opt/composer-require-checker --ignore-platform-req=ext-bcmath --ignore-platform-req=ext-intl \
 && ln -s /opt/composer-require-checker/vendor/bin/composer-require-checker /usr/local/bin/composer-require-checker
