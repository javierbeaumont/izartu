# The PHP of the dev and CI toolchain (`test`), which CI builds on every supported version.
ARG PHP_VERSION=8.5

# ===== The published image =====

# The base of PHP's build and of the image.
FROM alpine:3.24@sha256:be2656061b536c1883c4034790e21d0a09c5aaaf51968b89368a3c0371ea2c9a AS alpine

# PHP's source, signed by a release manager and checked to be the version named, which the signature does not prove.
FROM alpine AS php-source

ARG PHP_SOURCE_VERSION=8.5.12
ARG PHP_SHA256=08bc2eba2a588654ade1357174aa646b761556c862dda0678345ee29ed481209

ADD --checksum=sha256:${PHP_SHA256} \
    https://www.php.net/distributions/php-${PHP_SOURCE_VERSION}.tar.xz /php.tar.xz

# The signature by wget: an ADD without a checksum would fetch it on every build, cached or not.
RUN --mount=type=bind,source=docker/php-keys.asc,target=/php-keys.asc \
    apk add --no-cache gpg gpgv \
 && wget -q -O /php.tar.xz.asc "https://www.php.net/distributions/php-${PHP_SOURCE_VERSION}.tar.xz.asc" \
 && gpg --dearmor < /php-keys.asc > /php-keys.gpg \
 && gpgv --keyring /php-keys.gpg /php.tar.xz.asc /php.tar.xz \
 && tar -xf /php.tar.xz -C /tmp "php-${PHP_SOURCE_VERSION}/main/php_version.h" \
 && grep -qF "#define PHP_VERSION \"${PHP_SOURCE_VERSION}\"" "/tmp/php-${PHP_SOURCE_VERSION}/main/php_version.h"

# PHP, compiled with only the modules izartu uses.
FROM alpine AS php

COPY --from=php-source /php.tar.xz /usr/src/php.tar.xz

# The official image's hardening flags; PHP_UNAME stands for the build machine, which `php -i` would otherwise name.
ENV CFLAGS="-fstack-protector-strong -fpic -fpie -O2 -D_LARGEFILE_SOURCE -D_FILE_OFFSET_BITS=64" \
    CPPFLAGS="-fstack-protector-strong -fpic -fpie -O2 -D_LARGEFILE_SOURCE -D_FILE_OFFSET_BITS=64" \
    LDFLAGS="-Wl,-O1 -pie" \
    PHP_UNAME=Linux

WORKDIR /usr/src/php

# A pipeline fails when any of its commands does.
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

# The build takes the source's date, so the same source makes the same binaries.
RUN apk add --no-cache \
      build-base \
      # <asm/hwcap.h>, which PHP's crc32 includes on ARM.
      linux-headers \
      openssl-dev \
      pcre2-dev \
      pkgconf \
 && tar -xf ../php.tar.xz --strip-components=1 \
 && ./configure \
      --disable-all \
      --disable-cgi \
      # mb_ereg_*, which izartu does not use, would need oniguruma.
      --disable-mbregex \
      # The MySQL protocol's compression, which izartu does not use, would need zlib.
      --disable-mysqlnd-compression-support \
      # The JIT, off by default, only helps CPU-bound code; izartu's requests wait on MySQL.
      --disable-opcache-jit \
      --disable-phpdbg \
      --enable-filter \
      --enable-fpm \
      --enable-mbstring \
      --enable-mysqlnd \
      # An unknown option fails the build instead of being ignored.
      --enable-option-checking=fatal \
      --enable-pdo \
      --enable-session \
      --prefix=/usr/local \
      # Where an operator's php.ini and conf.d go, as in the official image.
      --with-config-file-path=/usr/local/etc/php \
      --with-config-file-scan-dir=/usr/local/etc/php/conf.d \
      # Alpine's PCRE2: nginx installs it anyway, and its fixes come with apk upgrade.
      --with-external-pcre \
      # MySQL over TLS.
      --with-openssl \
      --with-pdo-mysql=mysqlnd \
 && SOURCE_DATE_EPOCH="$(stat -c %Y main/php_version.h)" make -j"$(nproc)" \
 && install -D -s sapi/cli/php /usr/local/bin/php \
 && install -D -s sapi/fpm/php-fpm /usr/local/sbin/php-fpm \
    # The shared libraries the binaries load, as the apk dependencies (so:libssl.so.3) the image installs.
 && scanelf --needed --nobanner --format '%n#p' /usr/local/bin/php /usr/local/sbin/php-fpm \
      | tr , '\n' \
      | sort -u \
      | sed 's/^/so:/' > /rundeps \
 && find /usr/local -newer main/php_version.h -exec touch -h -r main/php_version.h {} +

# The image: Alpine, with nginx and PHP-FPM under s6 as www-data on port 8080, its layers from the least changing. First
# Alpine's packages and PHP, whose layers have to be reproducible.
FROM alpine AS system

# adduser dates /etc/shadow by the day and keeps backups in /etc/*-, which would change the layer daily.
RUN --mount=type=bind,from=php,source=/rundeps,target=/rundeps \
    --mount=type=bind,source=docker/pin-mtimes.sh,target=/pin-mtimes.sh \
    # apk's log records when it ran.
    xargs apk add --no-cache --no-logfile \
      nginx \
      s6 \
      < /rundeps \
    # Alpine has the www-data group (82), not its user.
 && adduser -u 82 -D -S -G www-data www-data \
 && sed -i 's/^\([^:]*:[^:]*:\)[0-9]*:/\1:/' /etc/shadow \
 && rm -f /etc/*- \
 && /pin-mtimes.sh

COPY --from=php /usr/local /usr/local

# The fixes Alpine published after the base image, dated by its release even if they upgrade it: the later layers carry
# the dates of the directories above their files.
RUN --mount=type=bind,source=docker/pin-mtimes.sh,target=/pin-mtimes.sh \
    --mount=type=bind,from=alpine,source=/etc/alpine-release,target=/base-release \
    apk upgrade --no-cache --no-logfile \
 && /pin-mtimes.sh /base-release

# Then the configuration, without the code.
FROM system AS base

COPY docker/rootfs/ /

# Numeric, so a runtime can check it is not root (Kubernetes' runAsNonRoot).
USER 82:82

WORKDIR /var/www/izartu

EXPOSE 8080

HEALTHCHECK --start-period=60s --start-interval=1s CMD ["wget", "-q", "-O", "/dev/null", "http://127.0.0.1:8080/"]

CMD ["entrypoint.sh"]

# The published image, with the code of a release archive (`git archive`) as the build context `source`: what a release
# download carries, VERSION stamped.
FROM base AS release

# hadolint ignore=DL3022
COPY --from=source . /var/www/izartu/

# ===== The dev and CI toolchain, never shipped =====

FROM php:${PHP_VERSION}-cli AS test

RUN docker-php-ext-install pdo_mysql

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git \
      jq \
      unzip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# composer-require-checker needs PHP 8.4: as a dev dependency, `composer install` would fail on 8.2 and 8.3.
ADD --checksum=sha256:35d1dfdd7aa94a750f4b4c9b0b22dd5e76b5e9e35fb910c8901f3dafd2ad2ab9 --chmod=755 \
    https://github.com/maglnet/ComposerRequireChecker/releases/download/4.24.0/composer-require-checker.phar \
    /usr/local/bin/composer-require-checker
