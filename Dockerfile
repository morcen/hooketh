# PHP Base stage
FROM php:8.2-fpm-alpine AS php-base

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev \
    oniguruma-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    supervisor \
    autoconf \
    gcc \
    g++ \
    make

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application code first
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-pgsql

# Development stage
FROM php-base AS development

# Install development dependencies (application code already copied in base stage)
RUN composer install --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Production stage
FROM php-base AS production

# Application code already copied in base stage

# Remove development files
RUN rm -rf \
    tests \
    .env.example \
    .gitignore \
    .editorconfig \
    .styleci.yml \
    phpunit.xml \
    webpack.mix.js \
    node_modules \
    package*.json

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Queue worker stage
FROM production AS queue-worker

# Override CMD to run queue worker
CMD ["php", "/var/www/html/artisan", "queue:work", "redis", "--queue=webhooks,default", "--sleep=3", "--tries=3", "--max-time=3600"]

# Scheduler stage  
FROM production AS scheduler

# Install cron
RUN apk add --no-cache dcron

# Copy crontab file
COPY docker/crontab /etc/crontabs/www-data

# Start cron
CMD ["crond", "-f"]
