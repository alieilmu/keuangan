# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 - Build aset frontend (Vite + Vue + Tailwind)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build


# ---------------------------------------------------------------------------
# Stage 2 - Dependensi PHP tanpa dev
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative


# ---------------------------------------------------------------------------
# Stage 3 - Runtime: Nginx + PHP-FPM dalam satu container (port 8080)
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS runtime

# Ekstensi yang dibutuhkan Laravel, PhpSpreadsheet (import/export Excel),
# dan minishlink/web-push (push notification).
RUN apk add --no-cache \
        nginx supervisor tzdata \
        icu-libs libzip libpng libjpeg-turbo freetype gmp oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev gmp-dev oniguruma-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql bcmath gmp intl zip gd exif opcache pcntl sockets \
    && docker-php-ext-enable opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

ENV TZ=Asia/Jakarta
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Kode aplikasi + vendor + aset hasil build.
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rw storage bootstrap/cache

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8080/up > /dev/null || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
