#!/bin/sh

# Copy .env.example to .env if missing
if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Generate APP_KEY if missing
if ! grep -q APP_KEY /var/www/html/.env || grep -q "base64:{$}" /var/www/html/.env; then
    php /var/www/html/artisan key:generate --force
fi

# Run migrations (optional, can comment if not needed)
# php /var/www/html/artisan migrate --force

# Start Apache in foreground
apache2-foreground
