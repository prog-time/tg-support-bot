#!/bin/bash

# ------------------------------------------------------------------------------
# Runs Hadolint on Dockerfiles passed as arguments.
# Only files named Dockerfile* are checked.
# Ignores specified Hadolint rules.
# Fails if any Dockerfile contains issues.
# ------------------------------------------------------------------------------

set -e

# -----------------------------------------
# Configuration
# -----------------------------------------
PROJECT_DIR="."

IGNORE_RULES="DL3008|DL3015"

# -----------------------------------------
# Get files from arguments
# -----------------------------------------
ALL_FILES=("$@")
DOCKERFILES=()

for FILE in "${ALL_FILES[@]}"; do
    if [[ "$(basename "$FILE")" == Dockerfile* ]]; then
        DOCKERFILES+=("$FILE")
    fi
done

if [[ ${#DOCKERFILES[@]} -eq 0 ]]; then
    echo -e "No Dockerfiles in provided files. Skipping Hadolint."
    exit 0
fi

# -----------------------------------------
# Run Hadolint
# -----------------------------------------
cd "$PROJECT_DIR" || {
  echo -e "Cannot cd to $PROJECT_DIR"
  exit 1
}

ERROR_FOUND=0

for FILE in "${DOCKERFILES[@]}"; do
    FULL_PATH="$PROJECT_DIR/$FILE"

    if [[ ! -f "$FULL_PATH" ]]; then
        echo -e "File $FULL_PATH not found. Skipping."
        continue
    fi

    if [[ -n "$IGNORE_RULES" ]]; then
        output=$(hadolint "$FULL_PATH" 2>&1 | grep -vE "$IGNORE_RULES" || true)
    else
        output=$(hadolint "$FULL_PATH" 2>&1 || true)
    fi

    if [[ -n "$output" ]]; then
        echo -e "Issues found in $FULL_PATH:"
        echo "$output"
        ERROR_FOUND=1
    else
        echo -e "$FULL_PATH passed Hadolint checks!"
    fi
done

if [[ $ERROR_FOUND -eq 0 ]]; then
    echo -e "All Dockerfiles passed Hadolint checks!"
else
    echo -e "Hadolint found issues in one or more Dockerfiles!"
    exit 1
fi
