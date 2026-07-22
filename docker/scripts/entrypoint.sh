#!/bin/bash
#
# Runs once at container start, before the service's real command (php-fpm /
# queue:work / schedule:work).
#
# Why this exists: docker-compose.yml bind-mounts the working copy over the
# whole image (`.:/var/www`), so vendor/, node_modules/ and public/build/
# built during `docker build` are hidden at runtime — they only exist on the
# host once someone runs `composer install` / `npm run build` by hand. Before
# this script, forgetting (or not yet reaching) that manual step made the
# first `/admin/*` request fail with ViteManifestNotFoundException instead of
# a clear, expected error. This makes the install self-healing: the `app`
# container installs whatever is missing on boot instead of requiring exact
# manual step ordering.
#
# Only the `app` service performs composer install / npm run build — `queue`
# and `scheduler` run the identical image against the identical bind-mounted
# directory, so having all three race the same install would waste resources
# at best and corrupt vendor/node_modules at worst. They just wait for `app`
# to finish (CONTAINER_ROLE is set per-service in docker-compose.yml).
set -euo pipefail

cd /var/www

VENDOR_READY="vendor/autoload.php"
ASSETS_READY="public/build/manifest.json"
ROLE="${CONTAINER_ROLE:-app}"

if [ "$ROLE" = "app" ]; then
    if [ ! -f "$VENDOR_READY" ]; then
        echo "[entrypoint] vendor/ missing — running composer install..."
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi

    if [ ! -f "$ASSETS_READY" ]; then
        echo "[entrypoint] public/build/manifest.json missing — building frontend assets..."
        if [ -f package-lock.json ]; then
            npm ci
        else
            npm install
        fi
        npm run build
    fi
else
    until [ -f "$VENDOR_READY" ] && [ -f "$ASSETS_READY" ]; do
        echo "[entrypoint] waiting for the app container to finish installing dependencies..."
        sleep 2
    done
fi

exec "$@"
