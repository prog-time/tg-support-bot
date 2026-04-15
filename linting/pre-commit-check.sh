#!/bin/bash

set -e

ALL_FILE_ARRAY=()
while IFS= read -r line; do
    ALL_FILE_ARRAY+=("$line")
done < <(git diff --cached --name-only --diff-filter=ACM || true)

NEW_FILE_ARRAY=()
while IFS= read -r line; do
    NEW_FILE_ARRAY+=("$line")
done < <(git diff --cached --name-only --diff-filter=A || true)

#echo "ALL_FILE_ARRAY по индексам:"
#for i in "${!ALL_FILE_ARRAY[@]}"; do
#    echo "[$i] = '${ALL_FILE_ARRAY[$i]}'"
#done

echo "Checking Dockerfiles with Hadolint..."
bash linting/check_scripts/check_hadolint.sh "${ALL_FILE_ARRAY[@]}"
echo "----------"

echo "Checking shell scripts with ShellCheck..."
bash linting/check_scripts/check_shellcheck.sh "${ALL_FILE_ARRAY[@]}"
echo "----------"

echo "Checking for tests..."
bash linting/check_scripts/find_test.sh "${ALL_FILE_ARRAY[@]}"
echo "----------"

# -----------------------------
# Run type checking (PHPStan)
# -----------------------------
echo "Running type checks (PHPStan)..."

# NEW FILES
bash linting/check_scripts/check_phpstan.sh strict "${NEW_FILE_ARRAY[@]}"
echo "----------"

# MODIFIED FILES
bash linting/check_scripts/check_phpstan.sh lenient "${ALL_FILE_ARRAY[@]}"
echo "----------"

# -----------------------------
# Fix code style (Pint)
# -----------------------------
echo "Fixing code style (Pint)..."
if [ ${#PHP_ALL_FILE_ARRAY[@]} -gt 0 ]; then
    bash linting/check_scripts/check_pint.sh "${PHP_ALL_FILE_ARRAY[@]}"
fi
echo "----------"

echo "🧑🏻‍💻 Running tests..."
bash linting/check_scripts/start_tests.sh "${ALL_FILE_ARRAY[@]}"
echo
