#!/bin/bash

set -e

# -----------------------------------------
# Find the project root
# -----------------------------------------
find_project_root() {
    local dir="$PWD"
    while [[ "$dir" != "/" ]]; do
        [[ -f "$dir/composer.json" ]] && echo "$dir" && return
        dir=$(dirname "$dir")
    done
    echo "Project root not found (composer.json)"
    exit 1
}

# -----------------------------------------
# Convert file path to namespace-classname
# -----------------------------------------
path_to_classname() {
    local path="$1"
    path="${path%.php}"
    path="${path#app/}"
    echo "${path//\//\\}"
}

# -----------------------------------------
# Find the test file path for a given app class
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
# Run a test file locally
# -----------------------------------------
run_test_file() {
    local test_file="$1"
    local relative_path="${test_file#$PROJECT_ROOT/}"

    echo "Running test: $relative_path"

    php artisan test "$relative_path"
    local exit_code=$?

    if [[ $exit_code -eq 0 ]]; then
        echo "Test passed: $relative_path"
        return 0
    else
        echo "Test failed: $relative_path"
        return 1
    fi
}

# -----------------------------------------
# Main function
# -----------------------------------------
main() {
    FILES=("$@")
    if [[ ${#FILES[@]} -eq 0 ]]; then
        echo "[RunTests] No files provided!"
        exit 0
    fi

    # Filter only PHP files
    PHP_FILES=()
    for f in "${FILES[@]}"; do
        [[ "$f" == *.php ]] && PHP_FILES+=("$f")
    done
    FILES=("${PHP_FILES[@]}")

    if [[ ${#FILES[@]} -eq 0 ]]; then
        echo "[RunTests] No PHP files to test!"
        exit 0
    fi

    PROJECT_ROOT=$(find_project_root)
    declare -a tests_to_run=()
    local has_failures=0

    add_unique_test() {
        local file="$1"
        for f in "${tests_to_run[@]}"; do
            [[ "$f" == "$file" ]] && return
        done
        tests_to_run+=("$file")
    }

    # Map PHP files to tests
    for file in "${FILES[@]}"; do
        [[ -z "$file" ]] && continue

        # If it's already a test file
        if [[ "$file" == tests/Unit/* || "$file" == tests/Feature/* ]]; then
            local abs_path="$PROJECT_ROOT/$file"
            [[ -f "$abs_path" ]] && add_unique_test "$abs_path"
        fi

        # If it's an app class, find corresponding test
        if [[ "$file" == app/* ]]; then
            local classname test_file
            classname=$(path_to_classname "$file")
            if test_file=$(find_test_file_by_class "$classname" "$PROJECT_ROOT"); then
                add_unique_test "$test_file"
            fi
        fi
    done

    if [[ ${#tests_to_run[@]} -eq 0 ]]; then
        echo "[RunTests] No tests found to run — skipping"
        exit 0
    fi

    # Run tests
    for test_file in "${tests_to_run[@]}"; do
        run_test_file "$test_file" || has_failures=1
    done

    if [[ $has_failures -eq 1 ]]; then
        echo "One or more tests failed"
        exit 1
    else
        echo "All tests passed successfully"
        exit 0
    fi
}

main "$@"
