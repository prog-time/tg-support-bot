#!/bin/bash
set -e

# -----------------------------
# CONFIG
# -----------------------------
EXCLUDE_PATTERNS=(
    "*Test" "*Search" "*Controller*" "*Console*" "*Jobs*"
    "*Models*" "*Resources*" "*Requests*" "*DTO*" "*Dtos*"
    "*Kernel*" "*Middleware*" "*config*" "*ValueObject*"
    "*Enum*" "*Exception*" "*migrations*" "*Seeder*"
    "*Mock*" "*api*" "*Providers*" "*Abstract*"
    "*resources*" "*Stubs*" "*TestCase*"
)

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
    echo -e 'Laravel project root not found (composer.json missing)\n'
    exit 1
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
    done
    return 0
}

# -----------------------------
# Extract class name with namespace from a PHP file
# -----------------------------
extract_classname_from_file() {
    local file="$1"

    if [[ ! -f "$file" && -n "$PROJECT_ROOT" ]]; then
        file="$PROJECT_ROOT/$file"
    fi

    if [[ ! -f "$file" ]]; then
        return 1
    fi

    local namespace
    namespace=$(grep -m1 "^namespace " "$file" | sed 's/namespace \(.*\);/\1/' | tr -d ' ')

    local classname
    classname=$(grep -m1 "^class " "$file" | sed 's/class \([a-zA-Z0-9_]*\).*/\1/')

    if [[ -n "$classname" ]]; then
        if [[ -n "$namespace" ]]; then
            echo -e "$namespace\\$classname"
        else
            echo -e "$classname"
        fi
    fi
}

# -----------------------------
# Find all test classes
# -----------------------------
find_test_classes() {
    local project_root="$1"
    find "$project_root/tests" -type f -name "*Test.php" 2>/dev/null |
        while IFS= read -r file; do
            extract_classname_from_file "$file"
        done | sort -u
}

# -----------------------------
# Analyze coverage for a single class
# -----------------------------
analyze_coverage() {
    local classname="$1"
    shift
    local test_classes=("$@")

    [[ ! $(should_be_tested "$classname"; echo $?) -eq 0 ]] && return 0

    # Remove "App\" prefix from classname for test path
    local classname_without_app="${classname#App\\}"
    local expected_test="Tests\\Unit\\${classname_without_app}Test"
    local found=0

    for test_class in "${test_classes[@]}"; do
        test_class="$(echo "$test_class" | tr -d '\r\n')"
        if [[ "$test_class" == "$expected_test" ]]; then
            found=1
            break
        fi
    done

    if [[ $found -eq 0 ]]; then
        echo -e "No found $expected_test"
        return 1
    fi

    return 0
}

# -----------------------------
# Main
# -----------------------------
main() {
    if [[ "$#" -eq 0 ]]; then
        echo 'No PHP files changed — skipping'
        exit 0
    fi

    PROJECT_ROOT=$(find_project_root)

    TEST_CLASSES=()
    while IFS= read -r line; do
        [[ -n "$line" ]] && TEST_CLASSES+=("$line")
    done < <(find_test_classes "$PROJECT_ROOT")

    HAS_MISSING_TESTS=0

    for file in "$@"; do
        if [[ -z "$file" ]]; then
            continue
        fi

        if [[ "${file##*.}" != "php" ]]; then
            continue
        fi

        classname=$(extract_classname_from_file "$file")
        if [[ -z "$classname" ]]; then
            continue
        fi

        analyze_coverage "$classname" "${TEST_CLASSES[@]}" || HAS_MISSING_TESTS=1
    done

    if [[ $HAS_MISSING_TESTS -eq 1 ]]; then
        echo -e "Some classes are missing tests! Failing CI."
        exit 1
    fi

    exit 0
}

main "$@"
