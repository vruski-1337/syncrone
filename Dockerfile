FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /var/www/html

COPY . .

EXPOSE 80
