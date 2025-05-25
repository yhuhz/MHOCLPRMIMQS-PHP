# Use the official PHP Apache image
FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && \
    apt-get install -y \
    curl \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev && \
    docker-php-ext-install \
    mysqli \
    pdo \
    pdo_mysql \
    zip \
    gd \
    mbstring \
    xml && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy only necessary files first (optimizes Docker caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copy .env file (use .env.example if .env doesn't exist in your repo)
COPY .env ./

# Copy the rest of the application
COPY . .

# Create storage directory if it doesn't exist
RUN mkdir -p /var/www/html/storage && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Configure Apache to use the correct document root
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

EXPOSE 80
CMD ["apache2-foreground"]