#!/bin/sh

# Copy .env.example to .env if missing
if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Generate APP_KEY if missing
php /var/www/html/artisan key:generate --force

# Optional: run migrations
# php /var/www/html/artisan migrate --force

# Start Apache in foreground
apache2-foreground
