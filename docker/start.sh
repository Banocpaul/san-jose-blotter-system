#!/bin/sh

set -e

PORT="${PORT:-10000}"

echo "Starting Barangay San Jose Blotter Management System..."
echo "Listening on port ${PORT}"

sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf

sed -E -i \
    "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" \
    /etc/apache2/sites-available/000-default.conf

# Never boot production with cache files copied from a developer machine.
echo "Clearing stale Laravel caches..."
php artisan optimize:clear

echo "Running database migrations..."
php artisan migrate --force

# Cache configuration, routes, events, and Blade views after Render's
# environment variables are available.
echo "Building Laravel production caches..."
php artisan optimize

if [ "${RUN_DEMO_SEED:-false}" = "true" ]; then
    echo "Running five-year demo data seeder..."

    php artisan db:seed \
        --class="Database\\Seeders\\FiveYearDemoDataSeeder" \
        --force

    echo "Demo data seeding finished."
fi

echo "Starting Apache..."

exec apache2-foreground
