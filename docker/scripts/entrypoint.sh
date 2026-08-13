#!/bin/bash
set -e

# ============================================
# КОНФИГУРАЦИЯ
# ============================================
MAX_ATTEMPTS=30
SLEEP_INTERVAL=2
DB_HOST="${DB_HOST:-pgdb}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-pet}"
DB_USERNAME="${DB_USERNAME:-pet}"
DB_PASSWORD="${DB_PASSWORD:-secret}"

# ============================================
# ЦВЕТА ДЛЯ ВЫВОДА
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
# 1. ПРОВЕРКА .ENV
# ============================================
check_env() {
    log_step "Проверка .env файла..."

    if [ ! -f .env ]; then
        log_error ".env файл не найден!"
        log_error "Скопируйте .env.example в .env и заполните переменные:"
        log_error "  cp .env.example .env"
        log_error "  vim .env"
        exit 1
    fi

    log_info ".env найден"
}

# ============================================
# 2. ПРОВЕРКА VENDOR
# ============================================
check_vendor() {
    log_step "Проверка vendor..."

    if [ ! -f vendor/autoload.php ]; then
        log_error "vendor/autoload.php не найден!"
        log_error "Пересоберите образ: docker compose build --no-cache"
        exit 1
    fi

    log_info "Vendor найден"
}

# ============================================
# 3. ОЖИДАНИЕ БД
# ============================================
wait_for_db() {
    log_step "Ожидание PostgreSQL (${DB_HOST}:${DB_PORT})..."

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
            log_error "PostgreSQL не ответил после ${MAX_ATTEMPTS} попыток (${elapsed}с)"
            if [ -n "$last_error" ]; then
                log_error "PDO: ${last_error}"
            fi
            log_error "Проверьте:"
            log_error "  - Запущен ли контейнер: docker compose ps pgdb"
            log_error "  - Совпадает ли DB_PASSWORD в .env с паролем volume (после смены пароля: make clean && make up)"
            exit 1
        fi

        echo -n "."
        sleep $SLEEP_INTERVAL
    done

    local elapsed=$(($(date +%s) - start_time))
    echo ""
    log_info "PostgreSQL готов (${elapsed}с)"
}

# ============================================
# 4. МИГРАЦИИ
# ============================================
run_migrations() {
    log_step "Проверка миграций..."

    if php artisan db:table --table=migrations > /dev/null 2>&1; then
        local pending=$(php artisan migrate:status 2>/dev/null | grep -c "pending" || echo "0")

        if [ "$pending" -gt 0 ]; then
            log_warning "Найдено ${pending} немигрированных миграций"
            log_step "Запуск миграций..."

            if php artisan migrate --force; then
                log_info "Миграции выполнены успешно"
            else
                log_error "Ошибка при выполнении миграций"
                exit 1
            fi
        else
            log_info "Все миграции уже применены"
        fi
    else
        log_warning "Таблица migrations не найдена (первый запуск)"
        log_step "Выполнение всех миграций..."

        if php artisan migrate --force; then
            log_info "Миграции выполнены успешно"
        else
            log_error "Ошибка при выполнении миграций"
            exit 1
        fi
    fi
}

# ============================================
# 5. СИДЫ (только для dev)
# ============================================
run_seeds() {
    if [ "$APP_ENV" != "production" ] && [ -f database/seeders/DatabaseSeeder.php ]; then
        if [ ! -f storage/.seeded ]; then
            log_step "Запуск сидов..."

            if php artisan db:seed --force; then
                touch storage/.seeded
                log_info "Сиды выполнены успешно"
            else
                log_warning "Ошибка при выполнении сидов (продолжаем)"
            fi
        fi
    fi
}

# ============================================
# 6. ОПТИМИЗАЦИЯ (для продакшена)
# ============================================
optimize_production() {
    if [ "$APP_ENV" = "production" ]; then
        log_step "Оптимизация для продакшена..."

        if php artisan optimize; then
            log_info "Оптимизация выполнена"
        else
            log_warning "Ошибка при оптимизации (продолжаем)"
        fi
    fi
}

# ============================================
# 7. ОЧИСТКА КЭША (для dev)
# ============================================
clear_cache() {
    if [ "$APP_ENV" != "production" ]; then
        log_step "Очистка кэша..."
        php artisan cache:clear > /dev/null 2>&1 || true
        php artisan view:clear > /dev/null 2>&1 || true
        php artisan config:clear > /dev/null 2>&1 || true
        php artisan route:clear > /dev/null 2>&1 || true
        log_info "Кэш очищен"
    fi
}

# ============================================
# 8. ОЖИДАНИЕ APP ДЛЯ WORKER
# ============================================
wait_for_app() {
    if [ "$CONTAINER_ROLE" = "worker" ]; then
        log_step "Ожидание готовности app (порт 9000)..."

        local attempt=0
        while ! nc -z app 9000 2>/dev/null; do
            attempt=$((attempt + 1))
            if [ $attempt -ge 30 ]; then
                log_error "App не ответил после 30 попыток"
                exit 1
            fi
            sleep 2
            echo -n "."
        done
        echo ""
        log_info "App готов!"
    fi
}

# ============================================
# ГЛАВНАЯ ЛОГИКА
# ============================================
main() {
    echo "🚀 Запуск entrypoint (режим: ${APP_ENV:-production})"
    echo "📦 Контейнер: ${CONTAINER_ROLE:-app}"
    echo ""

    # Проверяем .env
    check_env

    # Проверяем vendor
    check_vendor

    # Ждём БД
    wait_for_db

    # Очищаем кэш (dev)
    clear_cache

    # Запускаем миграции
    run_migrations

    # Запускаем сиды (dev)
    run_seeds

    # Оптимизация (prod)
    optimize_production

    # Ждём app для worker
    wait_for_app

    echo ""
    log_info "Entrypoint завершен успешно"
    echo "🚀 Запуск: $@"
    echo ""

    exec "$@"
}

# Запускаем
main "$@"
