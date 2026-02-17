FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    linux-headers \
    nodejs \
    npm \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install \
    intl \
    opcache \
    pdo_mysql \
    zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy entrypoint
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
