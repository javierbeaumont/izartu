# The PHP of the dev and CI toolchain (`test`), which CI builds on every supported version.
ARG PHP_VERSION=8.5

# ===== The published image =====

# PHP, compiled with only the modules izartu uses.
FROM alpine:3.24@sha256:be2656061b536c1883c4034790e21d0a09c5aaaf51968b89368a3c0371ea2c9a AS php

ARG PHP_SOURCE_VERSION=8.5.12
ARG PHP_SHA256=08bc2eba2a588654ade1357174aa646b761556c862dda0678345ee29ed481209

ADD --checksum=sha256:${PHP_SHA256} \
    https://www.php.net/distributions/php-${PHP_SOURCE_VERSION}.tar.xz /usr/src/php.tar.xz

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

# The image: Alpine, with nginx and PHP-FPM under s6 as www-data on port 8080, its layers from the least changing.
FROM alpine:3.24@sha256:be2656061b536c1883c4034790e21d0a09c5aaaf51968b89368a3c0371ea2c9a AS base

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

# The fixes Alpine published after the base image.
RUN --mount=type=bind,source=docker/pin-mtimes.sh,target=/pin-mtimes.sh \
    apk upgrade --no-cache --no-logfile \
 && /pin-mtimes.sh

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
      unzip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
