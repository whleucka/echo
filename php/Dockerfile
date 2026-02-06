FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
       pdo \
       pdo_mysql \
       mysqli \
       zip \
       mbstring \
       exif \
       intl \
       gd \
       bcmath \
    && docker-php-ext-enable pdo_mysql

# Install PCOV for code coverage
RUN pecl install pcov && docker-php-ext-enable pcov

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Copy any custom PHP ini
COPY ./local.ini /usr/local/etc/php/conf.d/local.ini
