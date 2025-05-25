FROM php:8.2-apache

# Install required extensions
RUN docker-php-ext-install pdo_mysql mysqli

WORKDIR /var/www/html
COPY . .

EXPOSE 80
CMD ["apache2-foreground"]