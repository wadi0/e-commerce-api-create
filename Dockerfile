# Step 1: PHP with Apache
FROM php:8.2-apache

# Step 2: System dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev zip unzip git curl libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Step 3: Enable Apache rewrite
RUN a2enmod rewrite

# Step 4: Set working directory
WORKDIR /var/www/html

# Step 5: Copy composer from official image
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Step 6: Copy project files
COPY . .

# Step 7: Copy .env if not exists
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Step 8: Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Step 9: Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Step 10: Clear caches
RUN php artisan config:clear || true
RUN php artisan cache:clear || true
RUN php artisan route:clear || true
RUN php artisan view:clear || true

# Step 11: Generate key
RUN php artisan key:generate --force

# Step 12: Expose port
EXPOSE 80

# Step 13: Start Apache
CMD ["apache2-foreground"]
