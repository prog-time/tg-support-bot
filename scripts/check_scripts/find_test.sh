#!/bin/bash

# Script to check if classes have corresponding tests

set -e

# -----------------------------
# CONFIG
# -----------------------------
EXCLUDE_PATTERNS=(
    "*Test" "*Search" "*Controller*" "*Console*" "*Jobs*"
    "*Models*" "*Resources*" "*Requests*" "*DTO*" "*Dtos*"
    "*Kernel*" "*Middleware*" "*config*" "*ValueObject*"
    "*Enum*" "*Exception*" "*Migration*" "*Seeder*"
    "*MockDto*" "*api*" "*Providers*" "*Abstract*"
)

# -----------------------------
# Colors
# -----------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# -----------------------------
# Output helpers
# -----------------------------
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

# -----------------------------
# Find project root
# -----------------------------
find_project_root() {
    local current_dir="$PWD"

    while [[ "$current_dir" != "/" ]]; do
        if [[ -f "$current_dir/composer.json" ]]; then
            echo "$current_dir"
            return 0
        fi
        current_dir=$(dirname "$current_dir")
    done

    error "Laravel project root not found (composer.json missing)"
    exit 1
}

# -----------------------------
# Path → ClassName
# -----------------------------
path_to_classname() {
    local path="$1"
    path="${path%.php}"
    path="${path#app/}"
    local classname="${path//\//\\}"

    echo "$classname"
}

# -----------------------------
# Determine if class should be tested
# -----------------------------
should_be_tested() {
    local classname="$1"

    for pattern in "${EXCLUDE_PATTERNS[@]}"; do
        if [[ "$classname" == "$pattern" ]]; then
            return 1
        fi

        if [[ "$classname" == *"config"* ]]; then
            return 1
        fi
    done

    return 0
}

# -----------------------------
# Get expected test class name
# -----------------------------
get_expected_test_classname() {
    local classname="$1"
    echo "Tests\Unit\\${classname}Test"
}

# -----------------------------
# Find all test classes
# -----------------------------
find_test_classes() {
    local project_root="$1"
    local test_classes=()

    # Include module tests
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

    # Collect test classes
    for path in "${test_paths[@]}"; do
        if [[ -d "$path" ]]; then
            while IFS= read -r -d '' file; do
                if [[ "$file" == *"Test.php" ]]; then
                    local classname
                    classname=$(extract_classname_from_file "$file")

                    if [[ -n "$classname" ]]; then
                        test_classes+=("$classname")
                    fi
                fi
            done < <(find "$path" -name "*.php" -type f -print0 2>/dev/null)
        fi
    done

    # Return unique class names
    printf '%s\n' "${test_classes[@]}" | sort -u
}

# -----------------------------
# Extract class name from a PHP file
# -----------------------------
extract_classname_from_file() {
    local file="$1"

    if [[ ! -f "$file" ]]; then
        return 1
    fi

    local namespace=""
    local classname=""

    namespace=$(grep -m1 "^namespace " "$file" | sed 's/namespace \(.*\);/\1/' | tr -d ' ')

    classname=$(grep -m1 "^class " "$file" | sed 's/class \([a-zA-Z0-9_]*\).*/\1/')

    if [[ -n "$namespace" && -n "$classname" ]]; then
        echo "${namespace}\\${classname}"
    fi
}

# -----------------------------
# Check if a test class exists
# -----------------------------
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

# -----------------------------
# Get file path of a test class
# -----------------------------
get_test_file_path() {
    local test_classname="$1"
    local project_root="$2"

    local path="${test_classname//\\//}"
    echo "${project_root}/${path}.php"
}

# -----------------------------
# Analyze if class has a test
# -----------------------------
analyze_coverage() {
    local app_class="$1"
    local project_root="$2"

    local normalized_classname
    normalized_classname=$(path_to_classname "$app_class")

    if ! should_be_tested "$normalized_classname"; then
        warning "Class does not require testing: $normalized_classname"
        echo "---"
        return 0
    fi

    local expected_test
    expected_test=$(get_expected_test_classname "$normalized_classname")

    # Load all test classes
    local test_classes_array=()
    while IFS= read -r line; do
        test_classes_array+=("$line")
    done < <(find_test_classes "$project_root")

    if has_test "$normalized_classname" "$expected_test" "${test_classes_array[@]}"; then
        success "Test found: $expected_test"
        echo "---"
        return 0
    else
        error "Please create test file: $expected_test"
        echo "---"
        return 1
    fi
}

# -----------------------------
# Main function
# -----------------------------
main() {
    COMMAND="$1"  # commit или push

    if [ "$COMMAND" = "commit" ]; then
        ALL_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)
    elif [ "$COMMAND" = "push" ]; then
        BRANCH=$(git rev-parse --abbrev-ref HEAD)
        ALL_FILES=$(git diff --name-only origin/$BRANCH --diff-filter=ACM | grep '\.php$' || true)
    else
        echo "Unknown command: $COMMAND"
        exit 1
    fi

    if [ -z "$ALL_FILES" ]; then
      warning " [FindTest] No tests required!"
      exit 0
    fi

    local status_analyze=1
    for app_class in $ALL_FILES; do
        local project_root
        project_root=$(find_project_root)

        if ! analyze_coverage "$app_class" "$project_root"; then
            status_analyze=0
        fi
    done

    if [ "$status_analyze" = 0 ]; then
        exit 1
    fi
}

main "$@"
