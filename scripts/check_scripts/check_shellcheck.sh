#!/bin/bash
set -e

# -----------------------------------------
# Configuration
# -----------------------------------------
DOCKER_SERVICE="app"
PROJECT_DIR="/home/multichat"

# Directories to check (relative to PROJECT_DIR)
DIRS=(
    "scripts"
    # "docker/scripts" # add more if needed
)
# -----------------------------------------

ERROR_FOUND=0

cd "$PROJECT_DIR" || { echo -e "❌ Cannot cd to $PROJECT_DIR"; exit 1; }

for DIR in "${DIRS[@]}"; do
    if [ ! -d "$DIR" ]; then
        echo -e "⚠️ Directory $DIR does not exist. Skipping."
        continue
    fi

    echo -e "ℹ️ Checking directory: $DIR"

    # Find all shell scripts
    sh_files=$(find "$DIR" -type f -name "*.sh")
    if [ -z "$sh_files" ]; then
        echo -e "No .sh files found in $DIR"
        continue
    fi

    for file in $sh_files; do
        echo -e "ℹ️ Checking $file..."

        # Run ShellCheck inside Docker (warnings and errors only)
        output=$(docker compose exec -T "$DOCKER_SERVICE" shellcheck --severity=warning "$file" 2>&1) || rc=$?
        if [ -n "$output" ]; then
            echo "$output"
        fi

        if [ "${rc:-0}" -ne 0 ]; then
            ERROR_FOUND=1
        fi
    done
done

if [[ $ERROR_FOUND -eq 0 ]]; then
    echo -e "✅ All shell scripts passed important ShellCheck checks!"
else
    echo -e "❌ ShellCheck found important issues!"
fi

exit $ERROR_FOUND
