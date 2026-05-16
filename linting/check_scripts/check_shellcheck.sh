#!/bin/bash
# ------------------------------------------------------------------------------
# Runs ShellCheck on shell scripts using local shellcheck binary.
# Scans provided .sh files and validates them.
# Reports warnings and errors using ShellCheck severity level "warning".
# Fails if any script contains issues.
# ------------------------------------------------------------------------------

set -e

# -----------------------------------------
# Configuration
# -----------------------------------------
# ShellCheck exclusions
EXCLUDED_RULES=(
    "SC2053"
)
# -----------------------------------------

ALL_FILES=("$@")

if ! command -v shellcheck >/dev/null 2>&1; then
    echo "shellcheck is not installed. Skipping."
    exit 0
fi

# Build exclude string from array
EXCLUDE_STRING=""
if [[ ${#EXCLUDED_RULES[@]} -gt 0 ]]; then
    EXCLUDE_STRING=$(IFS=,; echo "${EXCLUDED_RULES[*]}")
fi

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

    rc=0
    if [[ -n "$EXCLUDE_STRING" ]]; then
        output=$(shellcheck --severity=warning --exclude="$EXCLUDE_STRING" "$FILE" 2>&1) || rc=$?
    else
        output=$(shellcheck --severity=warning "$FILE" 2>&1) || rc=$?
    fi

    if [[ -n "$output" ]]; then
        echo "$output"
    fi

    if [[ "$rc" -ne 0 ]]; then
        ERROR_FOUND=1
    fi
done

if [[ $ERROR_FOUND -eq 0 ]]; then
    echo -e "All shell scripts passed ShellCheck!"
else
    echo -e "ShellCheck found issues!"
fi

exit $ERROR_FOUND
