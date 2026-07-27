FROM php:8.3-apache

# System dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions Laravel needs (incl. pgsql for Postgres)
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql mbstring exif pcntl bcmath gd

# Enable Apache rewrite
RUN a2enmod rewrite

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy app
COPY . .

# Install PHP deps (no dev for production)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Storage symlink
RUN php artisan storage:link || true

# Cache config/routes
RUN php artisan config:clear || true
RUN php artisan route:clear || true

# Run migrations during build (safe — ignores failure)
RUN php artisan migrate --force --no-interaction || true

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Entrypoint to handle Render's $PORT
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
