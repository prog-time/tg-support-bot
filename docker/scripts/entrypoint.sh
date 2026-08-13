#!/bin/bash
set -e

# ============================================
# CONFIG
# ============================================
MAX_ATTEMPTS=30
SLEEP_INTERVAL=2
DB_HOST="${DB_HOST:-pgdb}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-pet}"
DB_USERNAME="${DB_USERNAME:-pet}"
DB_PASSWORD="${DB_PASSWORD:-secret}"

# ============================================
# COLORS
# ============================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}✅${NC} $1"
}

log_error() {
    echo -e "${RED}❌${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}⚠️${NC} $1"
}

log_step() {
    echo "📌 $1"
}

# ============================================
# 1. CHECK .ENV
# ============================================
check_env() {
    log_step "Checking .env file..."

    if [ ! -f .env ]; then
        log_error ".env file not found!"
        log_error "Copy .env.example to .env and fill in the values:"
        log_error "  cp .env.example .env"
        log_error "  vim .env"
        exit 1
    fi

    log_info ".env found"
}

# ============================================
# 2. CHECK VENDOR
# ============================================
check_vendor() {
    log_step "Checking vendor..."

    if [ ! -f vendor/autoload.php ]; then
        log_error "vendor/autoload.php not found!"
        log_error "Rebuild the image: docker compose build --no-cache"
        exit 1
    fi

    log_info "Vendor found"
}

# ============================================
# 3. WAIT FOR DB
# ============================================
wait_for_db() {
    log_step "Waiting for PostgreSQL (${DB_HOST}:${DB_PORT})..."

    local attempt=0
    local start_time=$(date +%s)
    local last_error=""

    until last_error="$(php -r "
        try {
            new PDO(
                'pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}',
                '${DB_USERNAME}',
                '${DB_PASSWORD}'
            );
            exit(0);
        } catch (Exception \$e) {
            fwrite(STDERR, \$e->getMessage());
            exit(1);
        }
    " 2>&1)"; do

        attempt=$((attempt + 1))

        if [ $attempt -ge $MAX_ATTEMPTS ]; then
            local elapsed=$(($(date +%s) - start_time))
            echo ""
            log_error "PostgreSQL did not respond after ${MAX_ATTEMPTS} attempts (${elapsed}s)"
            if [ -n "$last_error" ]; then
                log_error "PDO: ${last_error}"
            fi
            log_error "Check:"
            log_error "  - Container is running: docker compose ps pgdb"
            log_error "  - DB_PASSWORD in .env matches the volume password (after changing the password: make clean && make up)"
            exit 1
        fi

        echo -n "."
        sleep $SLEEP_INTERVAL
    done

    local elapsed=$(($(date +%s) - start_time))
    echo ""
    log_info "PostgreSQL is ready (${elapsed}s)"
}

# ============================================
# 4. MIGRATIONS
# ============================================
run_migrations() {
    log_step "Checking migrations..."

    if php artisan db:table --table=migrations > /dev/null 2>&1; then
        local pending=$(php artisan migrate:status 2>/dev/null | grep -c "pending" || echo "0")

        if [ "$pending" -gt 0 ]; then
            log_warning "Found ${pending} pending migration(s)"
            log_step "Running migrations..."

            if php artisan migrate --force; then
                log_info "Migrations completed successfully"
            else
                log_error "Migration failed"
                exit 1
            fi
        else
            log_info "All migrations already applied"
        fi
    else
        log_warning "migrations table not found (first run)"
        log_step "Running all migrations..."

        if php artisan migrate --force; then
            log_info "Migrations completed successfully"
        else
            log_error "Migration failed"
            exit 1
        fi
    fi
}

# ============================================
# 5. SEEDS (dev only)
# ============================================
run_seeds() {
    if [ "$APP_ENV" != "production" ] && [ -f database/seeders/DatabaseSeeder.php ]; then
        if [ ! -f storage/.seeded ]; then
            log_step "Running seeders..."

            if php artisan db:seed --force; then
                touch storage/.seeded
                log_info "Seeders completed successfully"
            else
                log_warning "Seeding failed (continuing)"
            fi
        fi
    fi
}

# ============================================
# 6. OPTIMIZE (production)
# ============================================
optimize_production() {
    if [ "$APP_ENV" = "production" ]; then
        log_step "Optimizing for production..."

        if php artisan optimize; then
            log_info "Optimization completed"
        else
            log_warning "Optimization failed (continuing)"
        fi
    fi
}

# ============================================
# 7. CLEAR CACHE (dev)
# ============================================
clear_cache() {
    if [ "$APP_ENV" != "production" ]; then
        log_step "Clearing caches..."
        php artisan cache:clear > /dev/null 2>&1 || true
        php artisan view:clear > /dev/null 2>&1 || true
        php artisan config:clear > /dev/null 2>&1 || true
        php artisan route:clear > /dev/null 2>&1 || true
        log_info "Caches cleared"
    fi
}

# ============================================
# 8. WAIT FOR APP (workers)
# ============================================
wait_for_app() {
    if [ "$CONTAINER_ROLE" = "worker" ]; then
        log_step "Waiting for app readiness (port 9000)..."

        local attempt=0
        while ! nc -z app 9000 2>/dev/null; do
            attempt=$((attempt + 1))
            if [ $attempt -ge 30 ]; then
                log_error "App did not respond after 30 attempts"
                exit 1
            fi
            sleep 2
            echo -n "."
        done
        echo ""
        log_info "App is ready!"
    fi
}

# ============================================
# MAIN
# ============================================
main() {
    echo "🚀 Starting entrypoint (mode: ${APP_ENV:-production})"
    echo "📦 Container: ${CONTAINER_ROLE:-app}"
    echo ""

    check_env
    check_vendor
    wait_for_db
    clear_cache
    run_migrations
    run_seeds
    optimize_production
    wait_for_app

    echo ""
    log_info "Entrypoint finished successfully"
    echo "🚀 Exec: $@"
    echo ""

    exec "$@"
}

main "$@"
