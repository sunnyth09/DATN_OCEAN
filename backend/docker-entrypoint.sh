#!/bin/sh
set -e

echo "======================================="
echo " Ocean Backend - Entrypoint Script"
echo "======================================="

# -----------------------------------------------
# 1. Prepare required directories
# -----------------------------------------------
echo "[1/7] Preparing directory structure..."
mkdir -p /var/www/storage/app/public/thumbnails
mkdir -p /var/www/storage/framework/cache/data
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/logs
mkdir -p /var/www/bootstrap/cache
mkdir -p /var/www/storage/tmp

# -----------------------------------------------
# 2. Wait for MySQL
# -----------------------------------------------
echo "[2/7] Waiting for MySQL..."
MAX_TRIES=30
COUNT=0
while [ "$COUNT" -lt "$MAX_TRIES" ]; do
    if php -r "try { new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:'3306'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_TIMEOUT=>3]); echo 'OK'; } catch (Exception \$e) { exit(1); }" 2>/dev/null; then
        echo "  MySQL is ready!"
        break
    fi
    COUNT=$((COUNT + 1))
    echo "  Retrying ($COUNT/$MAX_TRIES)..."
    sleep 2
done

# -----------------------------------------------
# 3. Ensure Composer dependencies exist
# -----------------------------------------------
echo "[3/7] Preparing Composer dependencies..."
cd /var/www
if [ -f /var/www/vendor/autoload.php ]; then
    echo "  Vendor directory is ready."
else
    echo "  Vendor is missing. Restoring from image cache..."
    mkdir -p /var/www/vendor
    cp -a /opt/vendor/. /var/www/vendor/ 2>/dev/null || true

    if [ -f /var/www/vendor/autoload.php ]; then
        echo "  Vendor restored from image cache."
    else
        echo "  Vendor restore failed. Running fallback composer install..."
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi
fi

# -----------------------------------------------
# 4. Fix permissions for writable paths
# -----------------------------------------------
echo "[4/7] Fixing permissions for Storage & Cache..."
chown -R www-data:www-data /var/www/storage || true
chown -R www-data:www-data /var/www/bootstrap/cache || true
chown -R www-data:www-data /var/www/vendor || true

find /var/www/storage -type d -exec chmod 777 {} + || true
find /var/www/storage -type f -exec chmod 666 {} + || true
find /var/www/bootstrap/cache -type d -exec chmod 777 {} + || true

# -----------------------------------------------
# 5. Laravel setup tasks
# -----------------------------------------------
echo "[5/7] Laravel setup tasks..."
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:CHANGE_ME" ]; then
    php artisan key:generate --force
fi

php artisan package:discover --ansi || true
php artisan storage:link --force || true
php artisan config:clear || true
php artisan cache:clear || true

# -----------------------------------------------
# 6. Run migrations
# -----------------------------------------------
echo "[6/7] Running migrations..."
php artisan migrate --force --no-interaction || echo "WARNING: Migration failed."

# -----------------------------------------------
# 7. Configure cron
# -----------------------------------------------
echo "[7/8] Setting up Cron for Laravel Scheduler..."
{
echo "PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
echo "* * * * * cd /var/www && /usr/local/bin/php artisan schedule:run >> /var/www/storage/logs/cron.log 2>&1"
} | crontab -

service cron start || true
echo "  Cron daemon started successfully!"

# -----------------------------------------------
# 8. Start Service
# -----------------------------------------------
if [ $# -gt 0 ]; then
    echo "[8/8] Executing custom container command: $@"
    exec "$@"
fi

echo "[8/8] Starting PHP-FPM for Backend..."
echo "======================================="
echo " Backend READY on port 9000"
echo " Cron (Laravel Scheduler) RUNNING"
echo "======================================="

exec php-fpm

