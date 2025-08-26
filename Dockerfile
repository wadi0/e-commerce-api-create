FROM php:8.2-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev libpng-dev libonig-dev libxml2-dev zip unzip git curl \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Composer install
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy app files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Clear caches (fix artisan issues)
RUN php artisan config:clear || true
RUN php artisan cache:clear || true
RUN php artisan route:clear || true
RUN php artisan view:clear || true

# Generate app key if not exists
RUN if [ ! -f .env ]; then cp .env.example .env; fi && php artisan key:generate --force || true

CMD ["php-fpm"]
