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
NC='\033[0m' # No Color

info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
success() { echo -e "${GREEN}✅ $1${NC}"; }
warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; }

# -----------------------------------------
# Поиск корня проекта
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
# Преобразование пути в имя класса
# -----------------------------------------
path_to_classname() {
    local path="$1"
    path="${path%.php}"
    path="${path#app/}"
    echo "${path//\//\\}"
}

# -----------------------------------------
# Проверка, нужно ли тестировать класс
# -----------------------------------------
should_be_tested() {
    local classname="$1"

    local exclude_patterns=("*Controller*" "*DTO*" "*ValueObject*" "*Enum*" "*Exception*" "*Migration*" "*Seeder*")
    for pattern in "${exclude_patterns[@]}"; do
        [[ "$classname" == $pattern ]] && return 1
    done

    local testable_types=("Service" "Repository" "Helper" "Job" "Command" "Middleware" "Policy" "Rule" "Resource" "Request" "Model" "Observer" "Listener" "Mail" "Notification")
    for type in "${testable_types[@]}"; do
        [[ "$classname" == *"$type" ]] && return 0
    done

    return 1
}

get_expected_test_classname() {
    echo "Tests\\Unit\\$1Test"
}

find_test_class_path() {
    local test_classname="$1"
    local project_root="$2"
    local test_path="${test_classname//\\//}.php"
    local full_path="$project_root/tests/${test_path#*Tests/}"
    [[ -f "$full_path" ]] && echo "$full_path" && return 0
    return 1
}

# -----------------------------------------
# Запуск теста через SSH
# -----------------------------------------
run_test_for_class() {
    local test_classname="$1"

    # путь к файлу относительно корня проекта
    local test_file=$(find_test_class_path "$test_classname" "$PROJECT_ROOT")
    if [[ -z "$test_file" ]]; then
        error "Тест не найден: $test_classname"
        return 1
    fi

    # путь относительно PROJECT_DIR
    local relative_path="${test_file#$PROJECT_ROOT/}"

    info "Запуск теста на сервере: $test_classname"
    info "Файл: $(basename "$test_file")"

    ssh ${SERVER_USER}@${SERVER_HOST} \
        "cd ${PROJECT_DIR} && docker compose exec -T app php artisan test $relative_path"

    local exit_code=$?
    if [[ $exit_code -eq 0 ]]; then
        success "Тест пройден: $test_classname"
        return 0
    else
        error "Тест провален: $test_classname"
        return 1
    fi
}


# -----------------------------------------
# Анализ и запуск тестов для файла
# -----------------------------------------
analyze_and_run_tests() {
    local app_file="$1"
    local normalized_classname=$(path_to_classname "$app_file")

    if ! should_be_tested "$normalized_classname"; then
        warning "Класс не требует теста: $normalized_classname"
        echo "---"
        return 0
    fi

    local expected_test=$(get_expected_test_classname "$normalized_classname")

    if run_test_for_class "$expected_test"; then
        echo "---"
        return 0
    else
        echo "---"
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
    local has_failures=0

    while IFS= read -r app_file; do
        [[ -z "$app_file" ]] && continue
        analyze_and_run_tests "$app_file" || has_failures=1
    done <<< "$ALL_FILES"

    if [[ $has_failures -eq 1 ]]; then
        error "Один или несколько тестов не прошли"
        exit 1
    else
        success "Все тесты успешны"
        exit 0
    fi

}

main "$@"
