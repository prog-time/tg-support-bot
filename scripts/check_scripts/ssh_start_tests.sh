#!/bin/bash

set -e

# -----------------------------------------
# SSH + DOCKER SETTINGS
# -----------------------------------------
SERVER_USER="root"
SERVER_HOST="45.80.69.244"
PROJECT_DIR="/home/multichat"
# -----------------------------------------

# Colors for output
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
# Find the project root
# -----------------------------------------
find_project_root() {
    local dir="$PWD"
    while [[ "$dir" != "/" ]]; do
        [[ -f "$dir/composer.json" ]] && echo "$dir" && return
        dir=$(dirname "$dir")
    done
    error "Project root not found (composer.json)"
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
# Run a test file on the server via SSH + Docker
# -----------------------------------------
run_test_file() {
    local test_file="$1"
    # path relative to PROJECT_DIR on the server
    local relative_path="${test_file#$PROJECT_ROOT/}"

    info "Running test on server: $relative_path"

    ssh ${SERVER_USER}@${SERVER_HOST} \
        "cd ${PROJECT_DIR} && docker compose exec -T app php artisan test $relative_path"

    local exit_code=$?
    if [[ $exit_code -eq 0 ]]; then
        success "Test passed: $relative_path"
        return 0
    else
        error "Test failed: $relative_path"
        return 1
    fi
}

# -----------------------------------------
# Main function
# -----------------------------------------
main() {
    local COMMAND="$1"

    if [[ "$COMMAND" = "commit" ]]; then
        ALL_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)
    elif [[ "$COMMAND" = "push" ]]; then
        BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null)
        if [[ -z "$BRANCH" || "$BRANCH" = "HEAD" ]]; then
            error "Failed to determine branch"
            exit 1
        fi

        if ! git ls-remote --exit-code origin "$BRANCH" >/dev/null 2>&1; then
            warning "origin/$BRANCH does not exist — testing staged files"
            ALL_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)
        else
            ALL_FILES=$(git diff --name-only origin/"$BRANCH" --diff-filter=ACM | grep '\.php$' || true)
        fi
    else
        error "Unknown command: $COMMAND (commit|push)"
        exit 1
    fi

    if [[ -z "$ALL_FILES" ]]; then
        warning "[RunTests] No PHP files to test!"
        exit 0
    fi

    PROJECT_ROOT=$(find_project_root)
    has_failures=0
    declare -a tests_to_run=()

    # Add test to array if not already added
    add_unique_test() {
        local file="$1"
        for f in "${tests_to_run[@]}"; do
            [[ "$f" == "$file" ]] && return 0
        done
        tests_to_run+=("$file")
    }

    # Build a unique list of tests
    while IFS= read -r file; do
        [[ -z "$file" ]] && continue

        # If this is a test file
        if [[ "$file" == tests/Unit/* || "$file" == tests/Feature/* ]]; then
            local abs_path="$PROJECT_ROOT/$file"
            [[ -f "$abs_path" ]] && add_unique_test "$abs_path"
        fi

        # If this is an app class
        if [[ "$file" == app/* ]]; then
            local classname
            classname=$(path_to_classname "$file")

            local test_file
            test_file=$(find_test_file_by_class "$classname" "$PROJECT_ROOT")

            [[ -n "$test_file" ]] && add_unique_test "$test_file"
        fi
    done <<< "$ALL_FILES"

    # Run the tests
    for test_file in "${tests_to_run[@]}"; do
        run_test_file "$test_file" || has_failures=1
    done

    if [[ $has_failures -eq 1 ]]; then
        error "One or more tests failed"
        exit 1
    else
        success "All tests passed successfully"
        exit 0
    fi
}

main "$@"
