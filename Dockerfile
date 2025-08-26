# Base image
FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev zip unzip git curl libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

COPY . .

# Copy .env
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Fix permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Install composer dependencies without scripts first
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Run package discover manually after .env is present
RUN php artisan package:discover --ansi || true

# Clear caches
RUN php artisan config:clear || true
RUN php artisan cache:clear || true
RUN php artisan route:clear || true
RUN php artisan view:clear || true

# Generate key
RUN php artisan key:generate --force

EXPOSE 80
CMD ["apache2-foreground"]
