#!/bin/sh
set -e

cd /var/www/html

# Create .env file
if [ ! -f ".env" ]; then
cat > .env <<EOL
APP_NAME=${APP_NAME:-Laravel}
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-}
DB_CONNECTION=${DB_CONNECTION:-sqlite}
DB_DATABASE=${DB_DATABASE:-/var/www/html/database/database.sqlite}
CACHE_DRIVER=${CACHE_DRIVER:-file}
SESSION_DRIVER=${SESSION_DRIVER:-cookie}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}
LOG_CHANNEL=${LOG_CHANNEL:-stderr}
EOL
fi

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# SQLite database
if [ "$DB_CONNECTION" = "sqlite" ] && [ ! -f "$DB_DATABASE" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
    chown www-data:www-data "$DB_DATABASE"
    chmod 664 "$DB_DATABASE"
fi

# Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations
php artisan migrate --force

# Permissions
chown -R www-data:www-data /var/www/html
chmod -R 775 storage bootstrap/cache database

# Start Apache in foreground
apache2-foreground



# #!/bin/sh
# cd /var/www/html

# # Create .env file from environment variables
# cat > .env <<EOL
# APP_NAME=${APP_NAME:-Laravel}
# APP_ENV=${APP_ENV:-production}
# APP_KEY=${APP_KEY:-}
# APP_DEBUG=${APP_DEBUG:-false}
# APP_URL=${APP_URL:-}

# LOG_CHANNEL=${LOG_CHANNEL:-stderr}
# LOG_DEPRECATIONS_CHANNEL=${LOG_DEPRECATIONS_CHANNEL:-null}
# LOG_LEVEL=${LOG_LEVEL:-debug}

# DB_CONNECTION=${DB_CONNECTION:-sqlite}
# DB_DATABASE=${DB_DATABASE:-/var/www/html/database/database.sqlite}

# SESSION_DRIVER=${SESSION_DRIVER:-cookie}
# SESSION_LIFETIME=${SESSION_LIFETIME:-120}
# SESSION_SECURE_COOKIE=${SESSION_SECURE_COOKIE:-true}

# CACHE_DRIVER=${CACHE_DRIVER:-file}
# QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}
# EOL

# # Generate APP_KEY if not set
# if [ -z "$APP_KEY" ]; then
#   echo "WARNING: APP_KEY is not set. Generating a new one..."
#   php artisan key:generate --force
#   # Update the .env file with the new key
#   APP_KEY=$(grep '^APP_KEY=' .env | cut -d '=' -f2-)
#   sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
# fi

# # Create SQLite database if needed
# if [ "$DB_CONNECTION" = "sqlite" ] && [ ! -f "$DB_DATABASE" ]; then
#   touch "$DB_DATABASE"
#   chown www-data:www-data "$DB_DATABASE"
#   chmod 664 "$DB_DATABASE"
# fi

# # Prepare Laravel
# php artisan config:clear
# php artisan cache:clear
# php artisan config:cache
# php artisan route:cache
# php artisan view:cache

# # Run database migrations
# php artisan migrate --force

# # Fix permissions
# chown -R www-data:www-data /var/www/html
# chmod -R 775 storage bootstrap/cache database

# # Start Apache
# exec apache2-foreground
