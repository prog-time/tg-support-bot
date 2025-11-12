#!/bin/bash

set -e

# -----------------------------------------
# НАСТРОЙКИ SSH + DOCKER
# -----------------------------------------
SERVER_USER="root"
SERVER_HOST="45.80.69.244"
PROJECT_DIR="/home/multichat"
# -----------------------------------------

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
success() { echo -e "${GREEN}✅ $1${NC}"; }
warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; }

# -----------------------------------------
# Найти корень проекта
# -----------------------------------------
find_project_root() {
    local dir="$PWD"
    while [[ "$dir" != "/" ]]; do
        [[ -f "$dir/composer.json" ]] && echo "$dir" && return
        dir=$(dirname "$dir")
    done
    error "Не найден корень проекта (composer.json)"
    exit 1
}

# -----------------------------------------
# Преобразование пути файла в namespace-класс
# -----------------------------------------
path_to_classname() {
    local path="$1"
    path="${path%.php}"
    path="${path#app/}"
    echo "${path//\//\\}"
}

# -----------------------------------------
# Найти путь тестового файла по классу приложения
# -----------------------------------------
find_test_file_by_class() {
    local classname="$1"
    local project_root="$2"

    for dir in Unit Feature; do
        local test_file="$project_root/tests/$dir/${classname//\\//}Test.php"
        [[ -f "$test_file" ]] && echo "$test_file" && return 0
    done

    return 1
}

# -----------------------------------------
# Запуск теста на сервере через SSH + Docker
# -----------------------------------------
run_test_file() {
    local test_file="$1"
    # путь относительно PROJECT_DIR на сервере
    local relative_path="${test_file#$PROJECT_ROOT/}"

    info "Запуск теста на сервере: $relative_path"

    ssh ${SERVER_USER}@${SERVER_HOST} \
        "cd ${PROJECT_DIR} && docker compose exec -T app php artisan test $relative_path"

    local exit_code=$?
    if [[ $exit_code -eq 0 ]]; then
        success "Тест пройден: $relative_path"
        return 0
    else
        error "Тест провален: $relative_path"
        return 1
    fi
}

# -----------------------------------------
# Главная функция
# -----------------------------------------
main() {
    local COMMAND="$1"

    if [[ "$COMMAND" = "commit" ]]; then
        ALL_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)
    elif [[ "$COMMAND" = "push" ]]; then
        BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null)
        if [[ -z "$BRANCH" || "$BRANCH" = "HEAD" ]]; then
            error "Не удалось определить ветку"
            exit 1
        fi

        if ! git ls-remote --exit-code origin "$BRANCH" >/dev/null 2>&1; then
            warning "origin/$BRANCH не существует — тестируем staged"
            ALL_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)
        else
            ALL_FILES=$(git diff --name-only origin/"$BRANCH" --diff-filter=ACM | grep '\.php$' || true)
        fi
    else
        error "Неизвестная команда: $COMMAND (commit|push)"
        exit 1
    fi

    if [[ -z "$ALL_FILES" ]]; then
        success "[RunTests] Нет PHP-файлов для тестирования!"
        exit 0
    fi

    PROJECT_ROOT=$(find_project_root)
    has_failures=0
    declare -a tests_to_run=()

    add_unique_test() {
        local file="$1"
        for f in "${tests_to_run[@]}"; do
            [[ "$f" == "$file" ]] && return 0
        done
        tests_to_run+=("$file")
    }

    # Формируем уникальный список тестов
    while IFS= read -r file; do
        [[ -z "$file" ]] && continue

        # Если это тестовый файл
        if [[ "$file" == tests/Unit/* || "$file" == tests/Feature/* ]]; then
            local abs_path="$PROJECT_ROOT/$file"
            [[ -f "$abs_path" ]] && add_unique_test "$abs_path"
        fi

        # Если это класс приложения
        if [[ "$file" == app/* ]]; then
            local classname=$(path_to_classname "$file")
            local test_file=$(find_test_file_by_class "$classname" "$PROJECT_ROOT")
            [[ -n "$test_file" ]] && add_unique_test "$test_file"
        fi
    done <<< "$ALL_FILES"

    # Запуск тестов
    for test_file in "${tests_to_run[@]}"; do
        run_test_file "$test_file" || has_failures=1
    done

    if [[ $has_failures -eq 1 ]]; then
        error "Один или несколько тестов не прошли"
        exit 1
    else
        success "Все тесты успешны"
        exit 0
    fi
}

main "$@"
