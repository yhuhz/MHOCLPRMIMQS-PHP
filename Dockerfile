FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /var/www/html

# Copy project files to container
COPY . /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
