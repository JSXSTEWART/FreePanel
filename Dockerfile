# Multi-stage Dockerfile for FreePanel
# Stage 1: Build frontend
FROM node:20-alpine AS frontend-builder

WORKDIR /app/frontend

# Copy frontend package files
COPY frontend/package*.json ./

# Install frontend dependencies
RUN npm ci --quiet

# Copy frontend source
COPY frontend/ ./

# Build frontend
RUN npm run build


# Stage 2: PHP application
FROM php:8.3-fpm-alpine AS php-base

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    mysql-client \
    postgresql-client \
    redis \
    supervisor \
    nginx

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        gd \
        zip \
        intl \
        mbstring \
        bcmath \
        opcache \
        pcntl

# Install Redis PHP extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html


# Stage 3: Development image
FROM php-base AS development

# Install Xdebug for development
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/freepanel.ini
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Copy application files
COPY --chown=www-data:www-data . .

# Install PHP dependencies
RUN composer install --prefer-dist --no-interaction --no-dev --optimize-autoloader

# Copy built frontend from frontend-builder stage
COPY --from=frontend-builder --chown=www-data:www-data /app/frontend/dist ./public/dist

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Expose ports
EXPOSE 9000 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]


# Stage 4: Production image
FROM php-base AS production

# Copy PHP configuration (without Xdebug)
COPY docker/php/php.ini /usr/local/etc/php/conf.d/freepanel.ini

# Copy application files
COPY --chown=www-data:www-data . .

# Install PHP dependencies (production only, no dev dependencies)
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && composer clear-cache

# Copy built frontend from frontend-builder stage
COPY --from=frontend-builder --chown=www-data:www-data /app/frontend/dist ./public/dist

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Optimize Laravel for production
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Expose ports
EXPOSE 9000 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
