FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install Composer
RUN apt-get update && apt-get install -y curl unzip && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html

# Run Composer Install inside the container
RUN composer install

EXPOSE 80
CMD ["apache2-foreground"]
