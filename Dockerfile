FROM php:8.2-apache

# Install only what you need
RUN apt-get update && apt-get install -y libzip-dev && \
    docker-php-ext-install pdo_mysql zip

WORKDIR /var/www/html
COPY . .

EXPOSE 80
CMD ["apache2-foreground"]