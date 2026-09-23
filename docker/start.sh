#!/bin/sh

set -e

PORT="${PORT:-10000}"

echo "Starting Barangay San Jose Blotter Management System..."
echo "Listening on port ${PORT}"

sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf

sed -E -i \
    "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" \
    /etc/apache2/sites-available/000-default.conf

echo "Running database migrations..."
php artisan migrate --force

echo "Clearing configuration cache..."
php artisan config:clear

echo "Caching Laravel views..."
php artisan view:cache

echo "Starting Apache..."

exec apache2-foreground
