FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html

# Run composer install inside the container
RUN composer install

EXPOSE 80
CMD ["apache2-foreground"]
