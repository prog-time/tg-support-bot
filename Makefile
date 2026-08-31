# ============================================
# VARIABLES
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
# HELP
# ============================================
.PHONY: help
help:
	@echo ""
	@echo "$(GREEN)📋 Available commands:$(NC)"
	@echo ""
	@echo "$(BLUE)🚀 Start:$(NC)"
	@echo "  make init      - Initialize project (.env, Nginx config)"
	@echo "  make up        - Start the stack (mode from APP_ENV)"
	@echo "  make build     - Rebuild shared image (app/queue/scheduler)"
	@echo ""
	@echo "$(BLUE)🛠️  Manage:$(NC)"
	@echo "  make stop      - Stop all containers"
	@echo "  make reload    - Rebuild and restart all containers"
	@echo "  make down      - Stop and remove containers"
	@echo "  make clean     - Full cleanup (containers + volumes)"
	@echo ""
	@echo "$(BLUE)📊 Logs:$(NC)"
	@echo "  make logs           - Logs for all containers"
	@echo "  make logs-app       - App logs only"
	@echo "  make logs-nginx     - Nginx logs only"
	@echo "  make logs-queue     - Queue logs only"
	@echo "  make logs-scheduler - Scheduler logs only"
	@echo ""
	@echo "$(BLUE)🐚 Shell:$(NC)"
	@echo "  make shell           - Shell into app"
	@echo "  make shell-queue     - Shell into queue"
	@echo "  make shell-scheduler - Shell into scheduler"
	@echo "  make shell-db        - Shell into PostgreSQL"
	@echo ""
	@echo "$(BLUE)🗄️  Database:$(NC)"
	@echo "  make migrate   - Run migrations"
	@echo "  make fresh     - Reset DB + migrations + seeds"
	@echo "  make seed      - Run seeders"
	@echo "  make rollback  - Roll back migrations"
	@echo ""
	@echo "$(BLUE)🧹 Cache / queue / tests:$(NC)"
	@echo "  make clear          - Clear Laravel caches"
	@echo "  make optimize       - Optimize for production"
	@echo "  make queue-restart  - Restart queues"
	@echo "  make test           - Run tests"
	@echo "  make init-smoke     - Smoke-test make init (isolated)"
	@echo ""

# ============================================
# INIT
# ============================================
.PHONY: init
init:
	@bash docker/scripts/init-project.sh

# ============================================
# START
# ============================================
.PHONY: up
up:
	@if [ ! -f .env ]; then \
		echo "$(RED)❌ .env file not found!$(NC)"; \
		echo "$(YELLOW)💡 Run: make init$(NC)"; \
		exit 1; \
	fi
	@if [ -n "$(IS_PROD)" ]; then \
		echo "$(GREEN)🚀 Starting in production mode...$(NC)"; \
	else \
		echo "$(GREEN)🚀 Starting in development mode...$(NC)"; \
	fi
	$(DOCKER_COMPOSE) up -d --build
	@echo ""
	@echo "$(GREEN)✅ Done!$(NC)"
	@if [ -n "$(IS_PROD)" ]; then \
		echo "$(YELLOW)📱 App: $(or $(APP_URL_VAL),https://your-domain.com)$(NC)"; \
	else \
		echo "$(YELLOW)📱 App: $(or $(APP_URL_VAL),http://localhost)$(NC)"; \
	fi
	@if [ "$(NGINX_PORT_VAL)" = "8080:80" ]; then \
		echo "$(YELLOW)🔧 Nginx on port 8080 (proxied by an external web server)$(NC)"; \
	elif [ "$(NGINX_HTTPS_PORT_VAL)" = "443:443" ]; then \
		echo "$(YELLOW)🔧 Nginx exposed (HTTP + HTTPS)$(NC)"; \
	else \
		echo "$(YELLOW)🔧 Nginx exposed (HTTP)$(NC)"; \
	fi
	@echo ""

.PHONY: build
build:
	@echo "$(GREEN)🔨 Rebuilding image (target=$(DOCKERFILE_TARGET), tag=$(IMAGE_TAG))...$(NC)"
	$(DOCKER_COMPOSE) build app
	@echo "$(GREEN)✅ Done!$(NC)"

# ============================================
# MANAGE
# ============================================
.PHONY: stop
stop:
	@echo "$(YELLOW)⏹️  Stopping containers...$(NC)"
	$(DOCKER_COMPOSE) stop
	@echo "$(GREEN)✅ Done!$(NC)"

.PHONY: reload
reload:
	@echo "$(YELLOW)🔄 Rebuilding and restarting containers...$(NC)"
	@echo "$(YELLOW)📦 Stopping...$(NC)"
	$(DOCKER_COMPOSE) down
	@echo "$(YELLOW)🔨 Rebuilding (target=$(DOCKERFILE_TARGET))...$(NC)"
	$(DOCKER_COMPOSE) build app
	@echo "$(YELLOW)🚀 Starting...$(NC)"
	$(DOCKER_COMPOSE) up -d
	@echo ""
	@echo "$(GREEN)✅ Done!$(NC)"
	@if [ -n "$(IS_PROD)" ]; then \
		echo "$(YELLOW)📱 App: $(or $(APP_URL_VAL),https://your-domain.com)$(NC)"; \
	else \
		echo "$(YELLOW)📱 App: $(or $(APP_URL_VAL),http://localhost)$(NC)"; \
	fi
	@echo ""

.PHONY: down
down:
	@echo "$(YELLOW)⏹️  Stopping and removing containers...$(NC)"
	$(DOCKER_COMPOSE) down
	@echo "$(GREEN)✅ Done!$(NC)"

.PHONY: clean
clean:
	@echo "$(RED)🧹 Full cleanup (removing containers + volumes)...$(NC)"
	$(DOCKER_COMPOSE) down -v --remove-orphans
	docker system prune -f
	@echo "$(GREEN)✅ Done!$(NC)"

# ============================================
# LOGS
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
		echo "$(RED)❌ DB_USERNAME/DB_DATABASE are not set in .env$(NC)"; \
		exit 1; \
	fi
	$(DOCKER_COMPOSE) exec pgdb psql -U "$(DB_USERNAME_VAL)" -d "$(DB_DATABASE_VAL)"

# ============================================
# DATABASE
# ============================================
.PHONY: migrate fresh seed rollback
migrate:
	@echo "$(YELLOW)🗄️ Running migrations...$(NC)"
	$(PHP_EXEC) php artisan migrate --force
	@echo "$(GREEN)✅ Done!$(NC)"

fresh:
	@echo "$(RED)🗄️ Resetting DB + migrations + seeds...$(NC)"
	$(PHP_EXEC) php artisan migrate:fresh --seed --force
	@echo "$(GREEN)✅ Done!$(NC)"

seed:
	@echo "$(YELLOW)🌱 Running seeders...$(NC)"
	$(PHP_EXEC) php artisan db:seed --force
	@echo "$(GREEN)✅ Done!$(NC)"

rollback:
	@echo "$(YELLOW)↩️ Rolling back migrations...$(NC)"
	$(PHP_EXEC) php artisan migrate:rollback
	@echo "$(GREEN)✅ Done!$(NC)"

# ============================================
# LARAVEL / QUEUE / TESTS
# ============================================
.PHONY: clear optimize queue-restart test
clear:
	@echo "$(YELLOW)🧹 Clearing caches...$(NC)"
	$(PHP_EXEC) php artisan cache:clear
	$(PHP_EXEC) php artisan config:clear
	$(PHP_EXEC) php artisan view:clear
	$(PHP_EXEC) php artisan route:clear
	$(PHP_EXEC) php artisan optimize:clear
	@echo "$(GREEN)✅ Done!$(NC)"

optimize:
	@echo "$(YELLOW)⚡ Optimizing for production...$(NC)"
	$(PHP_EXEC) php artisan optimize
	@echo "$(GREEN)✅ Done!$(NC)"

queue-restart:
	@echo "$(YELLOW)🔄 Restarting queues...$(NC)"
	$(PHP_EXEC) php artisan queue:restart
	@echo "$(GREEN)✅ Done!$(NC)"

test:
	@echo "$(YELLOW)🧪 Running all tests...$(NC)"
	$(PHP_EXEC) php artisan test

.PHONY: init-smoke
init-smoke:
	@bash checks/scripts/init-smoke.sh

# ============================================
# DEFAULT
# ============================================
.DEFAULT_GOAL := help
