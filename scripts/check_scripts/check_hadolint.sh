#!/bin/bash
set -e

# -----------------------------------------
# Configuration
# -----------------------------------------
PROJECT_DIR="/home/multichat"

DOCKERFILES=(
    "Dockerfile"
    "docker/node/Dockerfile"
)

# Ignore rules Hadolint
IGNORE_RULES="DL3008|DL3015"

ERROR_FOUND=0

cd "$PROJECT_DIR" || { echo -e "❌ Cannot cd to $PROJECT_DIR"; exit 1; }

for FILE in "${DOCKERFILES[@]}"; do
    echo -e "🔍 Checking $FILE ..."

    if [[ ! -f "$PROJECT_DIR/$FILE" ]]; then
        echo -e "⚠️ File $FILE not found. Skipping."
        continue
    fi

    if [[ -n "$IGNORE_RULES" ]]; then
        output=$(hadolint "$FILE" 2>&1 | grep -vE "$IGNORE_RULES" || true)
    else
        output=$(hadolint "$FILE" 2>&1 || true)
    fi

    if [[ -n "$output" ]]; then
        echo -e "❌ Issues found in $FILE:"
        echo "$output"
        ERROR_FOUND=1
    else
        echo -e "✅ $FILE passed Hadolint checks!"
    fi
done

if [[ $ERROR_FOUND -eq 0 ]]; then
    echo -e "✅ All Dockerfiles passed Hadolint checks!"
else
    echo -e "❌ Hadolint found issues in one or more Dockerfiles!"
fi

exit $ERROR_FOUND
