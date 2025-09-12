#!/bin/bash

set -e

# проверка наличия тестов
echo "🔍 [1/4] Проверка наличия тестов..."
bash scripts/check_scripts/find_test.sh commit
echo

# исправления стиля кода
echo "🎨 [2/3] Исправление стиля кода (Pint)..."
bash scripts/check_scripts/check_pint.sh commit
echo

# проверка на наличие ошибок
echo "🧪 [3/3] Проверка типизации (PHPStan)..."
bash scripts/check_scripts/check_phpstan.sh commit
echo
