#!/bin/bash

set -e

# Цвета для вывода
GREEN='\033[0;32m'
NC='\033[0m' # Нет цвета

PROJECT_DIR=$(pwd)

echo -e "${GREEN}Запуск PHPStan...${NC}"
phpstan analyse "$PROJECT_DIR" --level=max

echo -e "${GREEN}Запуск Pint...${NC}"
pint --verbose

echo -e "${GREEN}Проверка завершена успешно!${NC}"

exit 1
