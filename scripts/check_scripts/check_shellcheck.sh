#!/bin/bash
set -e

# -----------------------------------------
# Configuration
# -----------------------------------------
DOCKER_SERVICE="app"
PROJECT_DIR="/home/multichat"
# -----------------------------------------

ALL_FILES=("$@")

# -----------------------------------------
# Run ShellCheck
# -----------------------------------------
cd "$PROJECT_DIR" || { echo "Cannot cd to $PROJECT_DIR"; exit 1; }

ERROR_FOUND=0

for FILE in "${ALL_FILES[@]}"; do
    if [[ ! -f "$FILE" ]]; then
        echo "File $FILE not found. Skipping."
        continue
    fi

    if [[ "${FILE##*.}" != "sh" ]]; then
        continue
    fi

    echo "Checking $FILE..."

    output=$(docker compose exec -T "$DOCKER_SERVICE" \
        shellcheck --severity=warning "$FILE" 2>&1) || rc=$?

    if [[ -n "$output" ]]; then
        echo "$output"
    fi

    if [[ "${rc:-0}" -ne 0 ]]; then
        ERROR_FOUND=1
    fi
done

if [[ $ERROR_FOUND -eq 0 ]]; then
    echo -e "All shell scripts passed ShellCheck!"
else
    echo -e "ShellCheck found issues!"
fi

exit $ERROR_FOUND
