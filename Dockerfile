# syntax=docker/dockerfile:1.7

# =============================================================================
# TG Support Bot — PHP-FPM image (multi-stage)
#
# Stages:
#   vendor    — Composer deps (lockfile-cached + BuildKit cache mount)
#   frontend  — Vite production assets
#   php-base  — PHP-FPM + extensions (changes rarely → long-lived cache)
#   app       — production runtime (no Node) — default compose target
#   app-dev   — app + Node/npm for in-container frontend DX
#
# Build args:
#   BUILD_ENV  production|prod → --no-dev + classmap-authoritative + opcache freeze
#              anything else  → require-dev
#
# Compose selects target via DOCKERFILE_TARGET (app | app-dev).
# =============================================================================

# -----------------------------------------------------------------------------
# 1) Composer dependencies
# -----------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

COPY composer.json composer.lock ./

ARG BUILD_ENV=production

RUN --mount=type=cache,target=/tmp/composer \
    if [ "$BUILD_ENV" = "production" ] || [ "$BUILD_ENV" = "prod" ]; then \
        composer install \
            --no-dev \
            --no-scripts \
            --no-interaction \
            --prefer-dist \
            --no-progress \
            --optimize-autoloader; \
    else \
        composer install \
            --no-scripts \
            --no-interaction \
            --prefer-dist \
            --no-progress \
            --optimize-autoloader; \
    fi

COPY . .

RUN --mount=type=cache,target=/tmp/composer \
    if [ "$BUILD_ENV" = "production" ] || [ "$BUILD_ENV" = "prod" ]; then \
        composer dump-autoload --optimize --classmap-authoritative --no-dev \
        && php artisan package:discover --ansi; \
    else \
        composer dump-autoload --optimize \
        && php artisan package:discover --ansi; \
    fi

# -----------------------------------------------------------------------------
# 2) Frontend (Vite) — lockfile layer cached separately from sources
# -----------------------------------------------------------------------------
FROM node:20-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./

RUN --mount=type=cache,target=/root/.npm \
    npm ci --no-fund --no-audit

COPY vite.app.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build \
    && rm -rf node_modules

# -----------------------------------------------------------------------------
# 3) PHP runtime base — heaviest layer, invalidates rarely
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS php-base

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# netcat-openbsd — entrypoint `nc -z app 9000`
# git/unzip/curl — composer DX inside the container
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        unzip \
        netcat-openbsd \
    && install-php-extensions \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

# -----------------------------------------------------------------------------
# 4) Production application image (default target)
# -----------------------------------------------------------------------------
FROM php-base AS app

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

ARG BUILD_ENV=production

RUN if [ "$BUILD_ENV" = "production" ] || [ "$BUILD_ENV" = "prod" ]; then \
        printf '%s\n' 'opcache.validate_timestamps=0' \
            > /usr/local/etc/php/conf.d/zz-opcache-prod.ini; \
    fi

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    LARAVEL_GIT_COMMIT=false \
    PATH="/var/www/vendor/bin:${PATH}"

WORKDIR /var/www

COPY --link --chown=33:33 . .
COPY --link --from=vendor --chown=33:33 /app/vendor ./vendor
COPY --link --from=frontend --chown=33:33 /app/public/build ./public/build

RUN rm -f bootstrap/cache/*.php \
    && mkdir -p \
        storage/logs \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache \
        /var/log/php \
        /tmp/composer \
    && chown -R 33:33 storage bootstrap/cache /var/log/php /tmp/composer \
    && find storage bootstrap/cache -type d -exec chmod 775 {} + \
    && find storage bootstrap/cache -type f -exec chmod 664 {} + \
    && chmod +x docker/scripts/entrypoint.sh

USER 33:33

EXPOSE 9000

ENTRYPOINT ["/bin/bash", "/var/www/docker/scripts/entrypoint.sh"]
CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# 5) Dev image — same as app + Node/npm (no layer-bloat trick; clean COPY)
# -----------------------------------------------------------------------------
FROM app AS app-dev

USER 0:0

COPY --from=node:20-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:20-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -sf ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && node --version \
    && npm --version

# Step-debugging (docker-compose.yml wires XDEBUG_MODE/XDEBUG_PORT and exposes
# 9003) — dev-only, kept out of the prod `app` stage.
RUN install-php-extensions xdebug

COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

USER 33:33
