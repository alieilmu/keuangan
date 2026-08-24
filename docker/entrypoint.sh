#!/bin/sh
set -e

cd /var/www/html

# Direktori runtime yang dibutuhkan Nginx & Laravel.
mkdir -p /run/nginx \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ -z "${APP_KEY}" ] && [ ! -f .env ]; then
    echo "[entrypoint] APP_KEY belum diset. Jalankan: docker compose run --rm app php artisan key:generate --show" >&2
fi

# Web container yang bertugas menyiapkan database (dilewati oleh worker/scheduler).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "[entrypoint] Menunggu database siap..."
    tries=0
    until php artisan db:monitor >/dev/null 2>&1 || [ "$tries" -ge 30 ]; do
        tries=$((tries + 1))
        sleep 2
    done

    echo "[entrypoint] Menjalankan migrasi..."
    php artisan migrate --force --isolated || php artisan migrate --force
fi

# Cache konfigurasi/route/view: wajib untuk performa produksi.
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

php artisan storage:link 2>/dev/null || true

exec "$@"
