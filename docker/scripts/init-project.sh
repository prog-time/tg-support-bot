#!/usr/bin/env bash
# Interactive project bootstrap: .env, optional SSL, nginx config.
# Invoked by: make init
#
# Rules:
# - .env.example keeps secrets/credentials empty
# - Non-empty keys in an existing .env are never overwritten
# - APP_KEY / DB_PASSWORD are generated only when empty
# - DB_DATABASE / DB_USERNAME are prompted only when empty (with defaults)
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DEFAULT_DB_DATABASE='tg_support'
DEFAULT_DB_USERNAME='tg_support'

get_env() {
    local key="$1"
    local file="${2:-.env}"
    grep -E "^${key}=" "$file" 2>/dev/null | head -1 | cut -d '=' -f2- | tr -d '\r' || true
}

env_is_empty() {
    local key="$1"
    local val
    val="$(get_env "$key")"
    [[ -z "$val" ]]
}

# Safe write even when value contains /, &, =, etc.
set_env() {
    local key="$1"
    local value="$2"
    local tmp
    tmp="$(mktemp)"
    if grep -qE "^${key}=" .env 2>/dev/null; then
        awk -v k="$key" -v v="$value" '
            index($0, k "=") == 1 { print k "=" v; next }
            { print }
        ' .env > "$tmp"
        mv "$tmp" .env
    else
        rm -f "$tmp"
        echo "${key}=${value}" >> .env
    fi
}

# Write only when key is missing or empty.
set_env_if_empty() {
    local key="$1"
    local value="$2"
    if env_is_empty "$key"; then
        set_env "$key" "$value"
        return 0
    fi
    return 1
}

# After copying .env.example, restore every non-empty value from a previous .env.
restore_non_empty_from() {
    local old_env="$1"
    local key value
    while IFS= read -r line || [[ -n "$line" ]]; do
        [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
        [[ "$line" != *=* ]] && continue
        key="${line%%=*}"
        value="${line#*=}"
        value="${value%$'\r'}"
        [[ -z "$key" || -z "$value" ]] && continue
        set_env "$key" "$value"
    done < "$old_env"
}

echo ""
echo -e "${GREEN}🚀 Инициализация проекта...${NC}"
echo ""

if [[ ! -f .env.example ]]; then
    echo -e "${RED}❌ .env.example не найден${NC}"
    exit 1
fi

OLD_ENV_BACKUP=""
if [[ -f .env ]]; then
    echo -e "${YELLOW}⚠️  .env файл уже существует${NC}"
    read -r -p "Перезаписать? (y/N) " REPLY || true
    if [[ ! "${REPLY:-}" =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}❌ Отменено${NC}"
        exit 1
    fi
    OLD_ENV_BACKUP="$(mktemp)"
    cp .env "$OLD_ENV_BACKUP"
fi

echo -e "${BLUE}📝 Копирование .env.example в .env...${NC}"
cp .env.example .env
if [[ -n "$OLD_ENV_BACKUP" ]]; then
    restore_non_empty_from "$OLD_ENV_BACKUP"
    rm -f "$OLD_ENV_BACKUP"
    echo -e "${GREEN}✅ .env обновлён (непустые значения сохранены)${NC}"
else
    echo -e "${GREEN}✅ .env создан${NC}"
fi

# --------------------------------------------
# Secrets / DB credentials — only if empty
# --------------------------------------------
if env_is_empty "APP_KEY"; then
    echo -e "${BLUE}🔑 Генерация APP_KEY...${NC}"
    if command -v php >/dev/null 2>&1; then
        APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
        set_env "APP_KEY" "$APP_KEY"
        echo -e "${GREEN}✅ APP_KEY сгенерирован${NC}"
    else
        echo -e "${YELLOW}⚠️  PHP не найден, пропускаем генерацию APP_KEY${NC}"
    fi
else
    echo -e "${GREEN}✅ APP_KEY уже задан — пропускаем${NC}"
fi

if env_is_empty "DB_PASSWORD"; then
    echo -e "${BLUE}🔑 Генерация DB_PASSWORD...${NC}"
    DB_PASSWORD="$(openssl rand -base64 32 2>/dev/null | tr -d '\n' || echo 'secret')"
    set_env "DB_PASSWORD" "$DB_PASSWORD"
    echo -e "${GREEN}✅ DB_PASSWORD сгенерирован${NC}"
    echo -e "${YELLOW}   Если Postgres volume уже был создан со старым паролем — после init выполните: make clean && make up${NC}"
else
    echo -e "${GREEN}✅ DB_PASSWORD уже задан — пропускаем${NC}"
fi

if env_is_empty "DB_DATABASE"; then
    echo ""
    read -r -p "DB_DATABASE [${DEFAULT_DB_DATABASE}]: " DB_DATABASE_INPUT || true
    DB_DATABASE_INPUT="${DB_DATABASE_INPUT:-$DEFAULT_DB_DATABASE}"
    set_env "DB_DATABASE" "$DB_DATABASE_INPUT"
    echo -e "${GREEN}✅ DB_DATABASE = ${DB_DATABASE_INPUT}${NC}"
else
    echo -e "${GREEN}✅ DB_DATABASE уже задан ($(get_env DB_DATABASE)) — пропускаем${NC}"
fi

if env_is_empty "DB_USERNAME"; then
    echo ""
    read -r -p "DB_USERNAME [${DEFAULT_DB_USERNAME}]: " DB_USERNAME_INPUT || true
    DB_USERNAME_INPUT="${DB_USERNAME_INPUT:-$DEFAULT_DB_USERNAME}"
    set_env "DB_USERNAME" "$DB_USERNAME_INPUT"
    echo -e "${GREEN}✅ DB_USERNAME = ${DB_USERNAME_INPUT}${NC}"
else
    echo -e "${GREEN}✅ DB_USERNAME уже задан ($(get_env DB_USERNAME)) — пропускаем${NC}"
fi

# --------------------------------------------
# App mode — only if APP_ENV empty
# --------------------------------------------
USE_PROD=false
if env_is_empty "APP_ENV"; then
    echo ""
    echo -e "${BLUE}⚙️  Выбор режима:${NC}"
    echo "  1) dev  - Режим разработки (Xdebug, Vite, сиды)"
    echo "  2) prod - Продакшен режим (оптимизация, без dev-инструментов)"
    echo ""
    read -r -p "Выберите режим (1/2) [1]: " APP_ENV_CHOICE || true
    APP_ENV_CHOICE="${APP_ENV_CHOICE:-1}"
    if [[ "$APP_ENV_CHOICE" == "2" ]]; then
        set_env "APP_ENV" "production"
        set_env_if_empty "APP_DEBUG" "false" || true
        echo -e "${GREEN}✅ Режим: production${NC}"
        USE_PROD=true
    else
        set_env "APP_ENV" "local"
        set_env_if_empty "APP_DEBUG" "true" || true
        echo -e "${GREEN}✅ Режим: development${NC}"
    fi
else
    CURRENT_APP_ENV="$(get_env APP_ENV)"
    echo -e "${GREEN}✅ APP_ENV уже задан (${CURRENT_APP_ENV}) — пропускаем${NC}"
    if [[ "$CURRENT_APP_ENV" == "production" || "$CURRENT_APP_ENV" == "prod" ]]; then
        USE_PROD=true
    fi
    if env_is_empty "APP_DEBUG"; then
        if [[ "$USE_PROD" == "true" ]]; then
            set_env "APP_DEBUG" "false"
        else
            set_env "APP_DEBUG" "true"
        fi
    fi
fi

# --------------------------------------------
# Domain — only if MAIN_DOMAIN empty
# --------------------------------------------
MAIN_DOMAIN="$(get_env MAIN_DOMAIN)"
if env_is_empty "MAIN_DOMAIN"; then
    echo ""
    echo -e "${BLUE}🌐 Настройка домена:${NC}"
    read -r -p "Введите домен (например, example.com) [localhost]: " MAIN_DOMAIN || true
    MAIN_DOMAIN="${MAIN_DOMAIN:-localhost}"

    if [[ "$MAIN_DOMAIN" != "localhost" && -n "$MAIN_DOMAIN" ]]; then
        set_env "MAIN_DOMAIN" "$MAIN_DOMAIN"
        if [[ "$USE_PROD" == "true" ]]; then
            APP_URL="https://${MAIN_DOMAIN}"
        else
            APP_URL="http://${MAIN_DOMAIN}"
        fi
        set_env_if_empty "APP_URL" "$APP_URL" || true
        echo -e "${GREEN}✅ MAIN_DOMAIN = ${MAIN_DOMAIN}${NC}"
        echo -e "${GREEN}✅ APP_URL = $(get_env APP_URL)${NC}"
    else
        echo -e "${YELLOW}⚠️  Домен не указан, используем localhost${NC}"
        set_env "MAIN_DOMAIN" "localhost"
        MAIN_DOMAIN="localhost"
        if [[ "$USE_PROD" == "true" ]]; then
            echo -e "${RED}❌ Для продакшена домен обязателен!${NC}"
            echo -e "${YELLOW}   Пожалуйста, укажите домен при следующем запуске make init${NC}"
            exit 1
        fi
        set_env_if_empty "APP_URL" "http://localhost" || true
        echo -e "${GREEN}✅ APP_URL = $(get_env APP_URL)${NC}"
    fi
else
    echo -e "${GREEN}✅ MAIN_DOMAIN уже задан (${MAIN_DOMAIN}) — пропускаем${NC}"
    if env_is_empty "APP_URL"; then
        if [[ "$USE_PROD" == "true" && "$MAIN_DOMAIN" != "localhost" ]]; then
            set_env "APP_URL" "https://${MAIN_DOMAIN}"
        elif [[ "$MAIN_DOMAIN" != "localhost" ]]; then
            set_env "APP_URL" "http://${MAIN_DOMAIN}"
        else
            set_env "APP_URL" "http://localhost"
        fi
    fi
fi

# --------------------------------------------
# SSL + Nginx — only if NGINX_PORT empty
# --------------------------------------------
USE_SSL=false
NGINX_CHOICE="1"

if ! env_is_empty "NGINX_PORT"; then
    echo -e "${GREEN}✅ NGINX_PORT уже задан ($(get_env NGINX_PORT)) — пропускаем${NC}"
    if [[ "$(get_env NGINX_PORT)" == "8080:80" ]]; then
        NGINX_CHOICE="2"
    fi
    if [[ "$(get_env NGINX_HTTPS_PORT)" == "443:443" ]]; then
        USE_SSL=true
    fi
    if env_is_empty "NGINX_HTTPS_PORT"; then
        set_env "NGINX_HTTPS_PORT" "127.0.0.1:8443:443"
    fi
else
    if [[ "$USE_PROD" == "true" && "$MAIN_DOMAIN" != "localhost" ]]; then
        echo ""
        echo -e "${BLUE}🔒 SSL сертификат:${NC}"
        read -r -p "Установить SSL сертификат для ${MAIN_DOMAIN}? (y/n) [y]: " SSL_CHOICE || true
        SSL_CHOICE="${SSL_CHOICE:-y}"
        if [[ "$SSL_CHOICE" == "y" || "$SSL_CHOICE" == "Y" ]]; then
            USE_SSL=true
            echo -e "${GREEN}✅ SSL будет установлен${NC}"
        else
            echo -e "${YELLOW}⚠️  SSL не будет установлен, сайт будет работать по HTTP${NC}"
        fi
    fi

    echo ""
    echo -e "${BLUE}🌐 Настройка Nginx:${NC}"
    echo "  1) Nginx наружу (порты 80:80, 443:443) — сам обрабатывает HTTP/HTTPS"
    echo "  2) Nginx внутри (порт 8080:80) — только для внутреннего прокси от внешнего веб-сервера (Caddy, Nginx, Traefik и т.д.)"
    echo ""
    read -r -p "Выберите вариант (1/2) [1]: " NGINX_CHOICE || true
    NGINX_CHOICE="${NGINX_CHOICE:-1}"

    if [[ "$NGINX_CHOICE" == "1" ]]; then
        echo -e "${GREEN}✅ Nginx будет наружу (порты 80, 443)${NC}"
        echo -e "${YELLOW}   Контейнерный Nginx сам обрабатывает HTTP и HTTPS запросы${NC}"
        set_env "NGINX_PORT" "80:80"
        if [[ "$USE_SSL" == "true" ]]; then
            set_env "NGINX_HTTPS_PORT" "443:443"
            echo -e "${GREEN}✅ NGINX_HTTPS_PORT = 443:443${NC}"
        else
            set_env "NGINX_HTTPS_PORT" "127.0.0.1:8443:443"
            echo -e "${YELLOW}⚠️  HTTPS наружу отключен (NGINX_HTTPS_PORT=127.0.0.1:8443:443)${NC}"
        fi
        echo -e "${GREEN}✅ NGINX_PORT = 80:80${NC}"
    else
        echo -e "${GREEN}✅ Nginx будет внутри (порт 8080)${NC}"
        echo -e "${YELLOW}   Контейнерный Nginx будет доступен только внутри Docker-сети${NC}"
        echo -e "${YELLOW}   Внешний веб-сервер будет проксировать на 127.0.0.1:8080${NC}"
        set_env "NGINX_PORT" "8080:80"
        set_env "NGINX_HTTPS_PORT" "127.0.0.1:8443:443"
        echo -e "${GREEN}✅ NGINX_PORT = 8080:80${NC}"
        echo -e "${YELLOW}⚠️  HTTPS наружу отключен (прокси на внешнем сервере)${NC}"
    fi
fi

if [[ "$USE_SSL" == "true" && "$NGINX_CHOICE" == "1" ]]; then
    echo ""
    echo -e "${YELLOW}📌 Установка SSL сертификата...${NC}"
    if ! command -v certbot >/dev/null 2>&1; then
        echo -e "${YELLOW}📦 Установка Certbot...${NC}"
        if [[ "$(uname)" == "Linux" ]] && command -v apt >/dev/null 2>&1; then
            sudo apt update 2>/dev/null || true
            sudo apt install -y certbot 2>/dev/null || echo -e "${YELLOW}⚠️  Не удалось установить certbot через apt${NC}"
        else
            echo -e "${YELLOW}⚠️  Автоустановка certbot поддерживается только через apt (Linux)${NC}"
            echo -e "${YELLOW}   Установите certbot вручную и перезапустите make init${NC}"
        fi
    else
        echo -e "${GREEN}✅ Certbot уже установлен${NC}"
    fi

    if command -v certbot >/dev/null 2>&1; then
        echo -e "${YELLOW}🔒 Выпуск сертификата для ${MAIN_DOMAIN}...${NC}"
        echo -e "${YELLOW}   (порт 80 должен быть свободен)${NC}"
        sudo certbot certonly --standalone -d "${MAIN_DOMAIN}" --non-interactive --agree-tos --email "admin@${MAIN_DOMAIN}" \
            || echo -e "${YELLOW}⚠️  Не удалось получить сертификат. Убедитесь что порт 80 свободен${NC}"

        if [[ -f "/etc/letsencrypt/live/${MAIN_DOMAIN}/fullchain.pem" ]]; then
            echo -e "${GREEN}✅ SSL сертификат получен${NC}"
            echo -e "${YELLOW}📌 Настройка автоматического обновления сертификата...${NC}"
            (crontab -l 2>/dev/null | grep -v "certbot renew"; echo "0 3 * * * /usr/bin/certbot renew --quiet --post-hook 'docker compose exec nginx nginx -s reload'") | crontab - 2>/dev/null || true
            echo -e "${GREEN}✅ Автообновление настроено (ежедневно в 3:00)${NC}"
        else
            echo -e "${YELLOW}⚠️  SSL сертификат не получен${NC}"
            USE_SSL=false
            set_env "NGINX_HTTPS_PORT" "127.0.0.1:8443:443"
        fi
    else
        echo -e "${YELLOW}⚠️  Certbot не установлен, пропускаем получение сертификата${NC}"
        USE_SSL=false
        set_env "NGINX_HTTPS_PORT" "127.0.0.1:8443:443"
    fi
fi

echo ""
echo -e "${YELLOW}📝 Генерация конфига Nginx...${NC}"
mkdir -p docker/nginx

if [[ "$USE_SSL" == "true" && "$MAIN_DOMAIN" != "localhost" ]]; then
    if [[ -f docker/nginx/default.ssl.conf.template ]]; then
        echo -e "${GREEN}✅ Найден шаблон с SSL (default.ssl.conf.template)${NC}"
        sed "s|__MAIN_DOMAIN__|${MAIN_DOMAIN}|g" docker/nginx/default.ssl.conf.template > docker/nginx/default.conf
        echo -e "${GREEN}✅ Конфиг с SSL создан для ${MAIN_DOMAIN}${NC}"
    else
        echo -e "${YELLOW}⚠️  Шаблон default.ssl.conf.template не найден, создаю вручную...${NC}"
        cat > docker/nginx/default.conf <<EOF
server {
    listen 80;
    server_name ${MAIN_DOMAIN} www.${MAIN_DOMAIN};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl;
    server_name ${MAIN_DOMAIN};
    ssl_certificate     /etc/letsencrypt/live/${MAIN_DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${MAIN_DOMAIN}/privkey.pem;
    client_max_body_size 60M;
    root /var/www/public;
    index index.php index.html;
    location / {
        add_header 'Access-Control-Allow-Origin' '*' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-Widget-Key' always;
        if (\$request_method = OPTIONS) {
            add_header 'Access-Control-Allow-Origin' '*' always;
            add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS' always;
            add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-Widget-Key' always;
            add_header 'Access-Control-Max-Age' 86400 always;
            return 204;
        }
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_param SCRIPT_FILENAME /var/www/public\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_param HTTP_AUTHORIZATION \$http_authorization;
    }
    location ~ /\.ht {
        deny all;
    }
}
EOF
        echo -e "${GREEN}✅ Конфиг с SSL создан для ${MAIN_DOMAIN}${NC}"
    fi
else
    TEMPLATE_DOMAIN="$MAIN_DOMAIN"
    if [[ -z "$TEMPLATE_DOMAIN" || "$TEMPLATE_DOMAIN" == "localhost" ]]; then
        TEMPLATE_DOMAIN="localhost"
    fi
    if [[ -f docker/nginx/default.http.conf.template ]]; then
        echo -e "${GREEN}✅ Найден шаблон без SSL (default.http.conf.template)${NC}"
        sed "s|__MAIN_DOMAIN__|${TEMPLATE_DOMAIN}|g" docker/nginx/default.http.conf.template > docker/nginx/default.conf
        echo -e "${GREEN}✅ Конфиг без SSL создан для ${TEMPLATE_DOMAIN}${NC}"
    else
        echo -e "${YELLOW}⚠️  Шаблон default.http.conf.template не найден, создаю вручную...${NC}"
        cat > docker/nginx/default.conf <<EOF
server {
    listen 80;
    server_name ${TEMPLATE_DOMAIN};
    root /var/www/public;
    client_max_body_size 60M;
    location / {
        add_header 'Access-Control-Allow-Origin' '*' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-Widget-Key' always;
        if (\$request_method = OPTIONS) {
            add_header 'Access-Control-Allow-Origin' '*' always;
            add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS' always;
            add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-Widget-Key' always;
            add_header 'Access-Control-Max-Age' 86400 always;
            return 204;
        }
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_param SCRIPT_FILENAME /var/www/public\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_param HTTP_AUTHORIZATION \$http_authorization;
    }
    location ~ /\.ht {
        deny all;
    }
}
EOF
        echo -e "${GREEN}✅ Конфиг без SSL создан для ${TEMPLATE_DOMAIN}${NC}"
    fi
fi

if [[ "$NGINX_CHOICE" == "2" ]]; then
    echo ""
    echo -e "${YELLOW}📌 Пример конфига для внешнего веб-сервера:${NC}"
    echo -e "${BLUE}---${NC}"
    if [[ "$MAIN_DOMAIN" != "localhost" && -n "$MAIN_DOMAIN" ]]; then
        if [[ "$USE_SSL" == "true" ]]; then
            echo "📌 Caddyfile (Caddy 2):"
            echo "${MAIN_DOMAIN} {"
            echo "    reverse_proxy localhost:8080"
            echo "}"
            echo ""
            echo "📌 Nginx:"
            cat <<EOF
server {
    listen 80;
    server_name ${MAIN_DOMAIN} www.${MAIN_DOMAIN};
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ${MAIN_DOMAIN};
    ssl_certificate     /etc/letsencrypt/live/${MAIN_DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${MAIN_DOMAIN}/privkey.pem;
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF
        else
            cat <<EOF
📌 Nginx (только HTTP):
server {
    listen 80;
    server_name ${MAIN_DOMAIN};
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    }
}
EOF
        fi
    else
        cat <<EOF
📌 Nginx (localhost):
server {
    listen 80;
    server_name localhost;
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    }
}
EOF
    fi
    echo -e "${BLUE}---${NC}"
    echo ""
    echo -e "${YELLOW}📌 Сохраните конфиг и перезагрузите внешний веб-сервер${NC}"
fi

echo ""
echo -e "${GREEN}✅ Инициализация завершена!${NC}"
echo ""
echo -e "${YELLOW}📋 Следующие шаги:${NC}"
echo -e "  1. Проверьте .env файл: ${BLUE}cat .env${NC}"
echo -e "  2. Проверьте конфиг Nginx: ${BLUE}cat docker/nginx/default.conf${NC}"
echo -e "  3. Запустите проект: ${BLUE}make up${NC}"
if [[ "$USE_SSL" == "true" ]]; then
    echo ""
    echo -e "${GREEN}✅ SSL сертификат установлен!${NC}"
    echo -e "${YELLOW}   Автообновление настроено (ежедневно в 3:00)${NC}"
fi
echo ""
