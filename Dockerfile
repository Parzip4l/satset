FROM php:8.2-fpm

# Install system dependencies
# libldap2-dev wajib ada untuk LDAP
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libldap2-dev \
    libzip-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure & Install PHP extensions
# Kita biarkan configure berjalan otomatis tanpa hardcode path x86_64
RUN docker-php-ext-configure ldap
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd ldap zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u 1000 -d /home/dev dev
RUN mkdir -p /home/dev/.composer && \
    chown -R dev:dev /home/dev

USER dev