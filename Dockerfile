FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    zip unzip curl git libzip-dev sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite zip \
    && a2enmod rewrite headers

# Copy Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache database

# Add entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]



# FROM php:8.2-apache

# # Install system dependencies
# RUN apt-get update && apt-get install -y \
#     zip unzip curl git libzip-dev sqlite3 libsqlite3-dev \
#     && docker-php-ext-install pdo pdo_mysql pdo_sqlite zip

# # Enable Apache modules
# RUN a2enmod rewrite headers

# # Configure Apache
# RUN echo "<VirtualHost *:80>\n\
#     DocumentRoot /var/www/html/public\n\
#     <Directory /var/www/html/public>\n\
#         AllowOverride All\n\
#         Require all granted\n\
#         Options -Indexes\n\
#     </Directory>\n\
#     ErrorLog /dev/stderr\n\
#     CustomLog /dev/stdout combined\n\
# </VirtualHost>" > /etc/apache2/sites-available/000-default.conf

# # Set working directory
# WORKDIR /var/www/html

# # Install Composer
# COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# # Copy application files (excluding .env)
# COPY . .

# # Install dependencies
# RUN composer install --no-dev --optimize-autoloader

# # Set initial permissions
# RUN chown -R www-data:www-data /var/www/html \
#     && find /var/www/html -type d -exec chmod 755 {} \; \
#     && find /var/www/html -type f -exec chmod 644 {} \; \
#     && chmod -R 775 storage bootstrap/cache database \
#     && mkdir -p database \
#     && touch database/database.sqlite \
#     && chmod 664 database/database.sqlite

# # Add entrypoint
# COPY entrypoint.sh /entrypoint.sh
# RUN chmod +x /entrypoint.sh

# EXPOSE 80

# ENTRYPOINT ["/entrypoint.sh"]
