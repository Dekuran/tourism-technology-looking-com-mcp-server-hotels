# Multi-stage build for Laravel MCP Server (Cloud Run)
# Stage 1: Install PHP dependencies with Composer (no-dev)
FROM composer:lts AS vendor
WORKDIR /app

# Copy composer manifests first for better layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader --no-scripts

# Bring in the full application
COPY . .

# Ensure optimized autoload after full copy
RUN composer dump-autoload -o

# Stage 2: Runtime - PHP CLI serving public/ via built-in server
# Cloud Run requires a single process listening on $PORT
FROM php:8.2-cli-alpine AS runtime

# Install required system deps and PHP extensions
# - intl (for Laravel locales)
# - zip (Composer/Laravel)
# - pdo_sqlite (default DB here is sqlite/file; adjust as needed)
RUN apk add --no-cache \
      icu-dev oniguruma-dev libzip-dev sqlite-dev bash tzdata \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j"$(nproc)" intl zip pdo pdo_sqlite

WORKDIR /app

# Copy app built assets (including vendor) from the build stage
COPY --from=vendor /app /app

# Provide production entrypoint (added separately at /usr/local/bin/entrypoint.sh)
# The entrypoint will:
# - ensure APP_KEY exists (generate if missing)
# - cache config (no route cache because closures exist)
# - start PHP's built-in server bound to $PORT
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
  && mkdir -p storage/framework/cache/data storage/logs bootstrap/cache \
  && chmod -R 777 storage bootstrap/cache

# Cloud Run will inject $PORT (default to 8080 for local runs)
ENV PORT=8080 \
    APP_ENV=production \
    LOG_CHANNEL=stderr \
    CACHE_DRIVER=file \
    SESSION_DRIVER=array

EXPOSE 8080

# Start Laravel via PHP built-in server targeting the public/ docroot (router handled by index.php)
CMD ["/usr/local/bin/entrypoint.sh"]