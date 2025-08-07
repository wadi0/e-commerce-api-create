#!/bin/sh
cd /var/www/html

# Generate APP_KEY if missing (safety check)
if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY is required!"
  exit 1
fi

# Prepare Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
php artisan migrate --force

# Start Apache
exec apache2-foreground
