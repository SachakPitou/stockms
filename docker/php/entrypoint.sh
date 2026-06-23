#!/bin/sh
set -e

# Always fix permissions on start
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Hand off to php-fpm
exec php-fpm