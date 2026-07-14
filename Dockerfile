FROM php:8.5-apache

RUN docker-php-ext-install pdo pdo_mysql

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite
