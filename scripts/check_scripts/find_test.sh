#!/bin/bash

# Скрипт для проверки наличия тестов для классов

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

    # Исключения
    local exclude_patterns=(
        "*Controller*"
        "*Traits*"
        "*Stub*"
        "*routes*"
        "*migrations*"
        "*Jobs*"
        "*DTO*"
        "*Model*"
        "*Test*"
        "*ValueObject*"
        "*Enum*"
        "*Exception*"
        "*Migration*"
        "*Seeder*"
    )

    for pattern in "${exclude_patterns[@]}"; do
        if [[ "$classname" == $pattern ]]; then
            return 1
        fi

        if [[ "$classname" == *"config"* ]]; then
            return 1
        fi
    done

    return 0
}

# Получение ожидаемого имени тестового класса
get_expected_test_classname() {
    local classname="$1"
    echo "Tests\Unit\\${classname}Test"
}

# Поиск всех тестовых классов
find_test_classes() {
    local project_root="$1"
    local test_classes=()

    # Пути для поиска тестов
    local test_paths=(
        "$project_root/tests/Unit"
        "$project_root/tests/Feature"
    )

    # Добавляем модули
    if [[ -d "$project_root/Modules" ]]; then
        for module_dir in "$project_root/Modules"/*; do
            if [[ -d "$module_dir" ]]; then
                test_paths+=("$module_dir/Tests/Unit")
                test_paths+=("$module_dir/Tests/Feature")
            fi
        done
    fi

    # Ищем тестовые файлы
    for path in "${test_paths[@]}"; do
        if [[ -d "$path" ]]; then
            while IFS= read -r -d '' file; do
                if [[ "$file" == *"Test.php" ]]; then
                    local classname=$(extract_classname_from_file "$file")
                    if [[ -n "$classname" ]]; then
                        test_classes+=("$classname")
                    fi
                fi
            done < <(find "$path" -name "*.php" -type f -print0 2>/dev/null)
        fi
    done

    # Возвращаем уникальные классы
    printf '%s\n' "${test_classes[@]}" | sort -u
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

# Проверка наличия теста
has_test() {
    local classname="$1"
    local expected_test="$2"
    shift 2
    local test_classes=("$@")

    for test_class in "${test_classes[@]}"; do
        if [[ "$test_class" == "$expected_test" ]]; then
            return 0
        fi
    done

    return 1
}

# Получение пути к файлу теста
get_test_file_path() {
    local test_classname="$1"
    local project_root="$2"

    local path="${test_classname//\\//}"
    echo "${project_root}/${path}.php"
}

# Основная функция анализа
analyze_coverage() {
    local app_class="$1"
    local project_root="$2"

    # Нормализуем имя класса
    local normalized_classname=$(path_to_classname "$app_class")

    # Проверяем, нужно ли тестировать
    if ! should_be_tested "$normalized_classname"; then
        warning "Класс не требует тестирования: $normalized_classname"
        echo "---"
        return 0
    fi

    # Получаем ожидаемое имя теста
    local expected_test=$(get_expected_test_classname "$normalized_classname")

    # Ищем все тестовые классы
    local test_classes_array=()
    while IFS= read -r line; do
        test_classes_array+=("$line")
    done < <(find_test_classes "$project_root")

    # Проверяем наличие теста
    if has_test "$normalized_classname" "$expected_test" "${test_classes_array[@]}"; then
        success "Тест найден: $expected_test"
        echo "---"
        return 0
    else
        local test_file_path=$(get_test_file_path "$expected_test" "$project_root")
        error "Создайте файл теста: $expected_test"
        echo "---"
        return 1
    fi
}

# Главная функция
main() {
    COMMAND="$1"  # commit или push

    if [ "$COMMAND" = "commit" ]; then
        ALL_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)
    elif [ "$COMMAND" = "push" ]; then
        BRANCH=$(git rev-parse --abbrev-ref HEAD)
        ALL_FILES=$(git diff --name-only origin/$BRANCH --diff-filter=ACM | grep '\.php$' || true)
    else
        echo "Неизвестная команда: $COMMAND"
        exit 1
    fi

    if [ -z "$ALL_FILES" ]; then
      echo "✅ [FindTest] Тесты не требуются!"
      exit 0
    fi

    local status_analyze=1
    for app_class in $ALL_FILES; do
        # Находим корень проекта
        local project_root=$(find_project_root)

        # Анализируем покрытие
        if ! analyze_coverage "$app_class" "$project_root"; then
            status_analyze=0
        fi
    done

    if [ "$status_analyze" = 0 ]; then
        exit 1
    fi
}

# Запуск скрипта
main "$@"
