#!/bin/sh
set -e

chmod -R 775 /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
mkdir -p /var/www/storage/logs
touch /var/www/storage/logs/laravel.log
chmod 664 /var/www/storage/logs/laravel.log 2>/dev/null || true

exec php-fpm