RUN composer install --no-dev --optimize-autoloader
RUN php artisan migrate --force
RUN php artisan config:cache
