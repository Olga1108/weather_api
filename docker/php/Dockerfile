ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-fpm-alpine

# Set working directory
WORKDIR /var/www/symfony

# Install system dependencies
RUN apk update && apk add --no-cache \
	build-base \
	curl \
	git \
	zip \
	unzip \
	nginx \
	supervisor \
	libzip-dev \
	libxml2-dev \
	icu-dev \
	autoconf # For pecl extensions like apcu/xdebug
# autoconf g++ make # For pecl/Xdebug if compiled from source

# Install PHP extensions
RUN docker-php-ext-configure intl && \
	docker-php-ext-install -j$(nproc) \
	intl \
	pdo_mysql \
	opcache \
	zip \
	xml \
	bcmath

# Install APCu
RUN pecl install apcu && docker-php-ext-enable apcu

# Install Xdebug (uncomment if needed for development)
# RUN pecl install xdebug && docker-php-ext-enable xdebug
# COPY ./xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create a non-root user
RUN addgroup -g 1000 -S appuser && \
	adduser -u 1000 -S appuser -G appuser -h /home/appuser -s /bin/sh && \
	chown -R appuser:appuser /var/www/symfony

# Permissions for logs and cache (Symfony specific)
# These directories will be created by Symfony, but we ensure the parent is writable
RUN mkdir -p /var/www/symfony/var/cache /var/www/symfony/var/log && \
	chown -R appuser:appuser /var/www/symfony/var

# Switch to non-root user
USER appuser

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]

# Optional: xdebug.ini for development
# [xdebug]
