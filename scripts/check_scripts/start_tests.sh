#!/bin/bash

# Скрипт для запуска тестов только для классов, изменённых в git (staged или в diff)

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Функция для вывода сообщений
info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
}

# Определение корневой директории проекта
find_project_root() {
    local current_dir="$PWD"

    while [[ "$current_dir" != "/" ]]; do
        if [[ -f "$current_dir/composer.json" ]]; then
            echo "$current_dir"
            return 0
        fi
        current_dir=$(dirname "$current_dir")
    done

    error "Не найден корень Laravel проекта (composer.json)"
    exit 1
}

# Преобразование пути файла в имя класса
path_to_classname() {
    local path="$1"

    # Убираем .php расширение
    path="${path%.php}"

    # Удаляем 'app/' в начале, если есть
    path="${path#app/}"

    # Заменяем / на \
    local classname="${path//\//\\}"
    echo "$classname"
}

# Проверка, нужно ли тестировать класс
should_be_tested() {
    local classname="$1"

    # Исключения — не тестируем
    local exclude_patterns=("*Controller*" "*DTO*" "*ValueObject*" "*Enum*" "*Exception*" "*Migration*" "*Seeder*")

    for pattern in "${exclude_patterns[@]}"; do
        if [[ "$classname" == "$pattern" ]]; then
            return 1
        fi
    done

    # Типы классов, которые нужно тестировать
    local testable_types=("Service" "Repository" "Helper" "Job" "Command" "Middleware" "Policy" "Rule" "Resource" "Request" "Model" "Observer" "Listener" "Mail" "Notification")

    for type in "${testable_types[@]}"; do
        if [[ "$classname" == *"$type" ]]; then
            return 0
        fi
    done

    return 1
}

# Получение ожидаемого имени тестового класса
get_expected_test_classname() {
    local classname="$1"
    echo "Tests\\Unit\\${classname}Test"
}

# Извлечение имени класса из файла
extract_classname_from_file() {
    local file="$1"

    if [[ ! -f "$file" ]]; then
        return 1
    fi

    local namespace=""
    local classname=""

    # Ищем namespace
    namespace=$(grep -m1 "^namespace " "$file" | sed 's/namespace \(.*\);/\1/' | tr -d ' ')

    # Ищем имя класса
    classname=$(grep -m1 "^class " "$file" | sed 's/class \([a-zA-Z0-9_]*\).*/\1/')

    if [[ -n "$namespace" && -n "$classname" ]]; then
        echo "${namespace}\\${classname}"
    fi
}

# Поиск всех тестовых классов и их путей
find_test_class_path() {
    local test_classname="$1"
    local project_root="$2"

    # Преобразуем имя класса в путь
    local test_path="${test_classname//\\//}.php"

    echo $test_path

    local full_path="$project_root/tests/${test_path#*Tests/}"

    if [[ -f "$full_path" ]]; then
        return 0
    fi
    return 1
}

# Запуск теста для класса
run_test_for_class() {
    local test_classname="$1"
    local project_root="$2"

    local test_file
    test_file=$(find_test_class_path "$test_classname" "$project_root")

    if [[ -z "$test_file" ]]; then
        error "Тестовый файл не найден для: $test_classname"
        return 1
    fi

    local classname
    classname=$(basename "$test_file" .php)

    echo "$classname"

    info "Запуск теста: $test_classname"
    info "Файл: $classname"

    # Запускаем тест через artisan с фильтром по имени класса
    cd "$project_root"
    if php artisan test --filter="$classname"; then
        success "Тест пройден: $test_classname"
        return 0
    else
        error "Тест провален: $test_classname"
        return 1
    fi
}

# Основная функция анализа и запуска
analyze_and_run_tests() {
    local app_file="$1"
    local project_root="$2"

    # Преобразуем путь к файлу в имя класса
    local normalized_classname
    normalized_classname=$(path_to_classname "$app_file")

    # Проверяем, нужно ли тестировать
    if ! should_be_tested "$normalized_classname"; then
        warning "Класс не требует тестирования: $normalized_classname"
        echo "---"
        return 0
    fi

    # Получаем ожидаемое имя теста
    local expected_test
    expected_test=$(get_expected_test_classname "$normalized_classname")

    echo "$expected_test"

    # Запускаем тест
    if run_test_for_class "$expected_test" "$project_root"; then
        echo "---"
        return 0
    else
        echo "---"
        return 1
    fi
}

# Главная функция
main() {
    local COMMAND="$1"

    if [ "$COMMAND" = "commit" ]; then
        ALL_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)
    elif [ "$COMMAND" = "push" ]; then
        BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null)
        if [ -z "$BRANCH" ] || [ "$BRANCH" = "HEAD" ]; then
            error "Не удалось определить текущую ветку"
            exit 1
        fi
        # Проверяем, существует ли origin/$BRANCH
        if ! git ls-remote --exit-code origin "$BRANCH" >/dev/null 2>&1; then
            warning "Ветка origin/$BRANCH не существует — тестируем все staged файлы"
            ALL_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)
        else
            ALL_FILES=$(git diff --name-only origin/"$BRANCH" --diff-filter=ACM | grep '\.php$' || true)
        fi
    else
        error "Неизвестная команда: $COMMAND (ожидается 'commit' или 'push')"
        exit 1
    fi

    if [ -z "$ALL_FILES" ]; then
        success "[RunTests] Нет PHP-файлов для тестирования!"
        exit 0
    fi

    local project_root
    project_root=$(find_project_root)

    local has_failures=0

    while IFS= read -r app_file; do
        if [[ -z "$app_file" ]]; then
            continue
        fi

        if ! analyze_and_run_tests "$app_file" "$project_root"; then
            has_failures=1
        fi
    done <<< "$ALL_FILES"

    if [ "$has_failures" -eq 1 ]; then
        error "❗ Один или несколько тестов не прошли или отсутствуют."
        exit 1
    else
        success "🎉 Все тесты для изменённых классов успешно пройдены!"
        exit 0
    fi
}

# Запуск скрипта
main "$@"
