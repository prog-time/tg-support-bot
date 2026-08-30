#!/bin/bash
set -e

# Функция для проверки выполнения команды
function run_step {
    local CMD="$1"
    local MSG="$2"
    echo "➡️  $MSG..."
    if ! eval "$CMD"; then
        echo "❌ Ошибка на этапе: $MSG"
        exit 1
    fi
}

echo "🔄 Обновление списка пакетов и обновление системы..."
#sudo apt update && sudo apt upgrade -y

# 🔹 Установка Certbot и плагина для Nginx (обязательный, но проверяем наличие)
echo "🔧 Проверка и установка Certbot и плагина для Nginx..."
if ! command -v certbot >/dev/null 2>&1; then
    echo "certbot не найден, пытаемся установить..."
    sudo apt install -y certbot python3-certbot-nginx || \
        echo "⚠️ Не удалось полностью установить certbot через apt. Если certbot уже установлен, продолжаем."
else
    echo "certbot уже установлен, установка пропущена."
fi

# Проверка наличия .env
if [ ! -f .env ]; then
    echo "❌ Ошибка: .env файл не найден"
    exit 1
fi

# Экспорт переменных
set -a
run_step "source .env" "Загрузка переменных из .env"
set +a

# Проверяем MAIN_DOMAIN
if [ -z "$MAIN_DOMAIN" ]; then
    echo "❌ Ошибка: MAIN_DOMAIN не задан в .env"
    exit 1
fi

# Получаем IP из DNS записи
DOMAIN_IP=$(dig +short "$MAIN_DOMAIN" | tail -n1)
if [ -z "$DOMAIN_IP" ]; then
    echo "❌ Ошибка: DNS запись для $MAIN_DOMAIN не найдена"
    exit 1
fi

# Получаем текущий публичный IP сервера
SERVER_IP=$(curl -s https://ifconfig.me)
if [ "$DOMAIN_IP" != "$SERVER_IP" ]; then
    echo "❌ Ошибка: IP $MAIN_DOMAIN ($DOMAIN_IP) не совпадает с IP сервера ($SERVER_IP)"
    exit 1
fi

echo "✅  DNS запись для $MAIN_DOMAIN указывает на сервер ($SERVER_IP)"

# Назначение владельца проекта
run_step "sudo chown -R www-data:www-data ." "Назначение владельца www-data:www-data для всех файлов проекта"

# Получение сертификатов
run_step "sudo certbot certonly --standalone -d $MAIN_DOMAIN" "Выпуск сертификата для $MAIN_DOMAIN"

# Конфигурация Nginx
run_step "sed 's|__MAIN_DOMAIN__|$MAIN_DOMAIN|g' docker/nginx/default.conf.template > docker/nginx/default.conf" "Создание конфигурации Nginx"

# Запуск Docker Compose
run_step "docker compose up -d --build" "Запуск Docker Compose"

# Обновление зависимостей Composer
run_step "docker compose exec app bash -c 'composer update'" "Обновление зависимостей PHP через Composer"

# Миграции базы данных
run_step "docker compose exec app bash -c 'php artisan migrate'" "Применение миграций базы данных"

# --force: без него key:generate не перезапишет плейсхолдер APP_KEY при APP_ENV=production.
run_step "docker compose exec app bash -c 'php artisan key:generate --force'" "Генерация ключа приложения Laravel"

# --force: первичная установка, подписчиков на push ещё нет.
run_step "docker compose exec app bash -c 'php artisan webpush:generate-vapid-keys --force'" "Генерация VAPID-ключей для Web Push"

# Очистка кэша приложения (в т.ч. закэшированных настроек/интеграций из settings)
run_step "docker compose exec app bash -c 'php artisan cache:clear'" "Очистка кэша приложения"

# 🔹 Перезапуск приложения
run_step "docker compose down" "Отключение сервисов"
run_step "docker compose up -d" "Перезапуск сервисов"

echo "✅ Скрипт выполнен успешно!"
