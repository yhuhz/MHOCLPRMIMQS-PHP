FROM php:8.2-apache

# Install system dependencies
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

# Enable Apache modules
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy only composer files first (optimize caching)
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy application files (excluding .env via .dockerignore)
COPY . .

# Fix dotenv path issue by creating empty .env (Render will use real vars)
RUN touch .env && \
    chown www-data:www-data .env && \
    chmod 644 .env

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    find /var/www/html -type d -exec chmod 755 {} \; && \
    find /var/www/html -type f -exec chmod 644 {} \;

# Add this to your Dockerfile to debug network:
RUN apt-get update && apt-get install -y iputils-ping dnsutils && \
ping -c 4 google.com && \
nslookup sql301.infinityfree.com

EXPOSE 80

# Add this to your Dockerfile (before CMD)
ENV DB_HOST=$DB_HOST
ENV DB_USER=$DB_USER
ENV DB_PASS=$DB_PASS
ENV DB_NAME=$DB_NAME
CMD ["apache2-foreground"]