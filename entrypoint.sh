#!/bin/bash
set -e

echo "Starting Laravel application..."

# Wait a moment for any database connections
sleep 2

# Copy .env.example if .env doesn't exist
if [ ! -f /var/www/html/.env ]; then
    echo "Creating .env file from .env.example..."
    if [ -f /var/www/html/.env.example ]; then
        cp /var/www/html/.env.example /var/www/html/.env
    else
        echo "Warning: .env.example not found, creating basic .env"
        cat > /var/www/html/.env << 'EOF'
APP_NAME=Laravel
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost
LOG_CHANNEL=stderr
SESSION_DRIVER=cookie
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
EOF
    fi
fi

# Set APP_KEY if not present
if ! grep -q "APP_KEY=base64:" /var/www/html/.env; then
    echo "Generating application key..."
    php /var/www/html/artisan key:generate --force --no-interaction
fi

# Run package discovery
echo "Running package discovery..."
php /var/www/html/artisan package:discover --ansi --no-interaction

# Cache configurations for better performance
echo "Caching configurations..."
php /var/www/html/artisan config:cache --no-interaction
php /var/www/html/artisan route:cache --no-interaction
php /var/www/html/artisan view:cache --no-interaction

# Optional: Run migrations (uncomment if needed)
 echo "Running database migrations..."
 php /var/www/html/artisan migrate --force --no-interaction

# Fix final permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

echo "Laravel application started successfully!"

# Start Apache
exec apache2-foreground
