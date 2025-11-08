#!/usr/bin/env sh
set -e

# entrypoint.sh — Production bootstrap for Laravel on Cloud Run
# - Ensures APP_KEY is present (generates if missing)
# - Prepares writable directories
# - Caches configuration/views for performance
# - Starts PHP built-in server bound to $PORT serving public/

echo "[$(date -Iseconds)] Bootstrapping Laravel..."

# Default PORT for local usage; Cloud Run injects $PORT
PORT="${PORT:-8080}"

# Ensure writable directories exist
mkdir -p storage/framework/cache/data storage/logs storage/framework/views bootstrap/cache
chmod -R 777 storage bootstrap/cache || true

# If APP_KEY is not set (no .env on Cloud Run), generate one and export it
if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is not set — generating a secure key"
  export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
fi

# Optimize Laravel configuration. Avoid route:cache due to closure routes in typical Laravel starter.
php artisan config:clear >/dev/null 2>&1 || true
php artisan config:cache || true
php artisan view:cache || true

# Log some diagnostic info
echo "[$(date -Iseconds)] APP_ENV=${APP_ENV:-production} PORT=${PORT} PHP_VERSION=$(php -r 'echo PHP_VERSION;')"

# Start PHP's built-in server, serving the public/ directory
# The router file (public/index.php) will handle all routing.
exec php -S 0.0.0.0:"$PORT" -t public public/index.php