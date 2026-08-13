# ============================================
# ПЕРЕМЕННЫЕ
# ============================================
SHELL := /bin/bash

DOCKER_COMPOSE = docker compose
PHP_EXEC = $(DOCKER_COMPOSE) exec -T app
PHP_EXEC_INTERACTIVE = $(DOCKER_COMPOSE) exec app

GREEN  := $(shell printf '\033[0;32m')
RED    := $(shell printf '\033[0;31m')
YELLOW := $(shell printf '\033[0;33m')
BLUE   := $(shell printf '\033[0;34m')
NC     := $(shell printf '\033[0m')

# Read a key from .env (empty if missing)
env_get = $(shell if [ -f .env ]; then grep -E '^$(1)=' .env | head -1 | cut -d '=' -f2- | tr -d '\r'; fi)

APP_ENV_VAL = $(call env_get,APP_ENV)
APP_URL_VAL = $(call env_get,APP_URL)
NGINX_PORT_VAL = $(call env_get,NGINX_PORT)
NGINX_HTTPS_PORT_VAL = $(call env_get,NGINX_HTTPS_PORT)
DB_USERNAME_VAL = $(call env_get,DB_USERNAME)
DB_DATABASE_VAL = $(call env_get,DB_DATABASE)

IS_PROD = $(filter production prod,$(APP_ENV_VAL))

# app = slim prod (no Node); app-dev = + Node/npm for in-container Vite DX
DOCKERFILE_TARGET ?= $(if $(IS_PROD),app,app-dev)
IMAGE_TAG ?= local
export DOCKERFILE_TARGET
export IMAGE_TAG

# ============================================
# ПОМОЩЬ
# ============================================
.PHONY: help
help:
	@echo ""
	@echo "$(GREEN)📋 Доступные команды:$(NC)"
	@echo ""
	@echo "$(BLUE)🚀 Запуск:$(NC)"
	@echo "  make init      - Инициализация проекта (создание .env, настройка Nginx)"
	@echo "  make up        - Запуск проекта (режим определяется из APP_ENV)"
	@echo "  make build     - Пересборка общего образа (app/queue/scheduler)"
	@echo ""
	@echo "$(BLUE)🛠️  Управление:$(NC)"
	@echo "  make stop      - Остановка всех контейнеров"
	@echo "  make reload    - Перезапуск всех контейнеров с пересборкой"
	@echo "  make down      - Остановка и удаление контейнеров"
	@echo "  make clean     - Полная очистка (контейнеры + volumes)"
	@echo ""
	@echo "$(BLUE)📊 Логи:$(NC)"
	@echo "  make logs           - Логи всех контейнеров"
	@echo "  make logs-app       - Логи только app"
	@echo "  make logs-nginx     - Логи только nginx"
	@echo "  make logs-queue     - Логи только queue"
	@echo "  make logs-scheduler - Логи только scheduler"
	@echo ""
	@echo "$(BLUE)🐚 Shell:$(NC)"
	@echo "  make shell           - Вход в контейнер app"
	@echo "  make shell-queue     - Вход в контейнер queue"
	@echo "  make shell-scheduler - Вход в контейнер scheduler"
	@echo "  make shell-db        - Вход в PostgreSQL"
	@echo ""
	@echo "$(BLUE)🗄️  База данных:$(NC)"
	@echo "  make migrate   - Запуск миграций"
	@echo "  make fresh     - Сброс БД + миграции + сиды"
	@echo "  make seed      - Запуск сидов"
	@echo "  make rollback  - Откат миграций"
	@echo ""
	@echo "$(BLUE)🧹 Очистка / очередь / тесты:$(NC)"
	@echo "  make clear          - Очистка кэша Laravel"
	@echo "  make optimize       - Оптимизация для продакшена"
	@echo "  make queue-restart  - Перезапуск очередей"
	@echo "  make test           - Запуск тестов"
	@echo "  make init-smoke     - Smoke-тест make init (изолированно)"
	@echo ""

# ============================================
# ИНИЦИАЛИЗАЦИЯ
# ============================================
.PHONY: init
init:
	@bash docker/scripts/init-project.sh

# ============================================
# ЗАПУСК
# ============================================
.PHONY: up
up:
	@if [ ! -f .env ]; then \
		echo "$(RED)❌ .env файл не найден!$(NC)"; \
		echo "$(YELLOW)💡 Запустите: make init$(NC)"; \
		exit 1; \
	fi
	@if [ -n "$(IS_PROD)" ]; then \
		echo "$(GREEN)🚀 Запуск в продакшен режиме...$(NC)"; \
	else \
		echo "$(GREEN)🚀 Запуск в режиме разработки...$(NC)"; \
	fi
	$(DOCKER_COMPOSE) up -d --build
	@echo ""
	@echo "$(GREEN)✅ Готово!$(NC)"
	@if [ -n "$(IS_PROD)" ]; then \
		echo "$(YELLOW)📱 Приложение: $(or $(APP_URL_VAL),https://your-domain.com)$(NC)"; \
	else \
		echo "$(YELLOW)📱 Приложение: $(or $(APP_URL_VAL),http://localhost)$(NC)"; \
	fi
	@if [ "$(NGINX_PORT_VAL)" = "8080:80" ]; then \
		echo "$(YELLOW)🔧 Nginx внутри на порту 8080 (прокси от внешнего веб-сервера)$(NC)"; \
	elif [ "$(NGINX_HTTPS_PORT_VAL)" = "443:443" ]; then \
		echo "$(YELLOW)🔧 Nginx наружу (HTTP + HTTPS)$(NC)"; \
	else \
		echo "$(YELLOW)🔧 Nginx наружу (HTTP)$(NC)"; \
	fi
	@echo ""

.PHONY: build
build:
	@echo "$(GREEN)🔨 Пересборка образа (target=$(DOCKERFILE_TARGET), tag=$(IMAGE_TAG))...$(NC)"
	$(DOCKER_COMPOSE) build app
	@echo "$(GREEN)✅ Готово!$(NC)"

# ============================================
# УПРАВЛЕНИЕ
# ============================================
.PHONY: stop
stop:
	@echo "$(YELLOW)⏹️  Остановка контейнеров...$(NC)"
	$(DOCKER_COMPOSE) stop
	@echo "$(GREEN)✅ Готово!$(NC)"

.PHONY: reload
reload:
	@echo "$(YELLOW)🔄 Перезапуск контейнеров с пересборкой...$(NC)"
	@echo "$(YELLOW)📦 Остановка...$(NC)"
	$(DOCKER_COMPOSE) down
	@echo "$(YELLOW)🔨 Пересборка (target=$(DOCKERFILE_TARGET))...$(NC)"
	$(DOCKER_COMPOSE) build app
	@echo "$(YELLOW)🚀 Запуск...$(NC)"
	$(DOCKER_COMPOSE) up -d
	@echo ""
	@echo "$(GREEN)✅ Готово!$(NC)"
	@if [ -n "$(IS_PROD)" ]; then \
		echo "$(YELLOW)📱 Приложение: $(or $(APP_URL_VAL),https://your-domain.com)$(NC)"; \
	else \
		echo "$(YELLOW)📱 Приложение: $(or $(APP_URL_VAL),http://localhost)$(NC)"; \
	fi
	@echo ""

.PHONY: down
down:
	@echo "$(YELLOW)⏹️  Остановка и удаление контейнеров...$(NC)"
	$(DOCKER_COMPOSE) down
	@echo "$(GREEN)✅ Готово!$(NC)"

.PHONY: clean
clean:
	@echo "$(RED)🧹 Полная очистка (удаление контейнеров + volumes)...$(NC)"
	$(DOCKER_COMPOSE) down -v --remove-orphans
	docker system prune -f
	@echo "$(GREEN)✅ Готово!$(NC)"

# ============================================
# ЛОГИ
# ============================================
.PHONY: logs logs-app logs-nginx logs-queue logs-scheduler
logs:
	$(DOCKER_COMPOSE) logs -f --tail=100

logs-app:
	$(DOCKER_COMPOSE) logs -f --tail=100 app

logs-nginx:
	$(DOCKER_COMPOSE) logs -f --tail=100 nginx

logs-queue:
	$(DOCKER_COMPOSE) logs -f --tail=100 queue

logs-scheduler:
	$(DOCKER_COMPOSE) logs -f --tail=100 scheduler

# ============================================
# SHELL
# ============================================
.PHONY: shell shell-queue shell-scheduler shell-db
shell:
	$(PHP_EXEC_INTERACTIVE) bash

shell-queue:
	$(DOCKER_COMPOSE) exec queue bash

shell-scheduler:
	$(DOCKER_COMPOSE) exec scheduler bash

shell-db:
	@if [ -z "$(DB_USERNAME_VAL)" ] || [ -z "$(DB_DATABASE_VAL)" ]; then \
		echo "$(RED)❌ DB_USERNAME/DB_DATABASE не заданы в .env$(NC)"; \
		exit 1; \
	fi
	$(DOCKER_COMPOSE) exec pgdb psql -U "$(DB_USERNAME_VAL)" -d "$(DB_DATABASE_VAL)"

# ============================================
# БАЗА ДАННЫХ
# ============================================
.PHONY: migrate fresh seed rollback
migrate:
	@echo "$(YELLOW)🗄️ Запуск миграций...$(NC)"
	$(PHP_EXEC) php artisan migrate --force
	@echo "$(GREEN)✅ Готово!$(NC)"

fresh:
	@echo "$(RED)🗄️ Сброс БД + миграции + сиды...$(NC)"
	$(PHP_EXEC) php artisan migrate:fresh --seed --force
	@echo "$(GREEN)✅ Готово!$(NC)"

seed:
	@echo "$(YELLOW)🌱 Запуск сидов...$(NC)"
	$(PHP_EXEC) php artisan db:seed --force
	@echo "$(GREEN)✅ Готово!$(NC)"

rollback:
	@echo "$(YELLOW)↩️ Откат миграций...$(NC)"
	$(PHP_EXEC) php artisan migrate:rollback
	@echo "$(GREEN)✅ Готово!$(NC)"

# ============================================
# LARAVEL / QUEUE / TESTS
# ============================================
.PHONY: clear optimize queue-restart test
clear:
	@echo "$(YELLOW)🧹 Очистка кэша...$(NC)"
	$(PHP_EXEC) php artisan cache:clear
	$(PHP_EXEC) php artisan config:clear
	$(PHP_EXEC) php artisan view:clear
	$(PHP_EXEC) php artisan route:clear
	$(PHP_EXEC) php artisan optimize:clear
	@echo "$(GREEN)✅ Готово!$(NC)"

optimize:
	@echo "$(YELLOW)⚡ Оптимизация для продакшена...$(NC)"
	$(PHP_EXEC) php artisan optimize
	@echo "$(GREEN)✅ Готово!$(NC)"

queue-restart:
	@echo "$(YELLOW)🔄 Перезапуск очередей...$(NC)"
	$(PHP_EXEC) php artisan queue:restart
	@echo "$(GREEN)✅ Готово!$(NC)"

test:
	@echo "$(YELLOW)🧪 Запуск всех тестов...$(NC)"
	$(PHP_EXEC) php artisan test

.PHONY: init-smoke
init-smoke:
	@bash checks/scripts/init-smoke.sh

# ============================================
# ДЕФОЛТ
# ============================================
.DEFAULT_GOAL := help
