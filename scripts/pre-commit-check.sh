#!/bin/bash

set -e

# исправления стиля кода
echo "🎨 Исправление стиля кода (Pint)..."
bash scripts/check_scripts/check_pint.sh commit
echo

# проверка наличия тестов
echo "🔍 Проверка наличия тестов..."
bash scripts/check_scripts/find_test.sh commit
echo

# проверка работы тестов
echo "🧑🏻‍💻 Проверка работы тестов..."
bash scripts/check_scripts/ssh_start_tests.sh commit
echo

# проверка на наличие ошибок
echo "🧪 Проверка типизации (PHPStan)..."
bash scripts/check_scripts/check_phpstan.sh commit
echo

