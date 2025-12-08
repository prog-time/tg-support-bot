#!/bin/bash

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
# CHECK NEW FILES
# -----------------------------

COMMAND="$1"  # either "commit" or "push"

if [ "$COMMAND" = "commit" ]; then
    # Only new files (status A = Added) ending with .php
    NEW_FILES=$(git diff --cached --name-only --diff-filter=A | grep '\.php$')

    # Filter out:
    # - files in the tests/ folder
    # - files ending with *Test.php
    FILTERED_FILES=$(echo "$NEW_FILES" | grep -v '/tests/' | grep -v 'Test\.php$')

    if [ -z "$FILTERED_FILES" ]; then
        warning "[PHPStan] No new PHP files to check (or all are tests)"
    else
        echo "🔍 Found new PHP files. Running PHPStan only on new files (excluding tests)"
        ./vendor/bin/phpstan analyse --no-progress --error-format=table $FILTERED_FILES
        if [ $? -ne 0 ]; then
            echo "❌ NEW FILES! PHPStan found type errors (MANDATORY)"
            exit 1
        fi
    fi
fi

# -----------------------------
# CHECK MODIFIED FILES
# -----------------------------

BASELINE_FILE=".phpstan-error-count.json"
BLOCK_COMMIT=0

if [ "$COMMAND" = "commit" ]; then
    # Added, Copied, or Modified files
    ALL_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)
elif [ "$COMMAND" = "push" ]; then
    BRANCH=$(git rev-parse --abbrev-ref HEAD)
    ALL_FILES=$(git diff --name-only origin/$BRANCH --diff-filter=ACM | grep '\.php$' || true)
else
    echo "Unknown command: $COMMAND"
    exit 1
fi

# Initialize baseline file if missing
if [ ! -f "$BASELINE_FILE" ]; then
    echo "{}" > "$BASELINE_FILE"
fi

if [ -z "$ALL_FILES" ]; then
  warning "[PHPStan] No PHP files to check."
  exit 0
fi

echo "🔍 [PHPStan] Checking files"

for FILE in $ALL_FILES; do
    echo "📄 Checking: $FILE"

    # Count new errors
    ERR_NEW=$(vendor/bin/phpstan analyse --error-format=raw --no-progress "$FILE" 2>/dev/null | grep -c '^')
    ERR_OLD=$(jq -r --arg file "$FILE" '.[$file] // empty' "$BASELINE_FILE")

    if [ -z "$ERR_OLD" ]; then
        echo "🆕 File not checked before. It has $ERR_NEW errors."
        ERR_OLD=$ERR_NEW
    fi

    # Set target: allow at most one new error compared to baseline
    TARGET=$((ERR_OLD - 1))
    [ "$TARGET" -lt 0 ] && TARGET=0

    if [ "$ERR_NEW" -le "$TARGET" ]; then
        echo "✅ Improved: was $ERR_OLD, now $ERR_NEW"
        jq --arg file "$FILE" --argjson errors "$ERR_NEW" '.[$file] = $errors' "$BASELINE_FILE" > "$BASELINE_FILE.tmp" && mv "$BASELINE_FILE.tmp" "$BASELINE_FILE"
    else
        echo "❌ Errors: $ERR_NEW (must be ≤ $TARGET)"
        vendor/bin/phpstan analyse --no-progress --error-format=table "$FILE"
        jq --arg file "$FILE" --argjson errors "$ERR_OLD" '.[$file] = $errors' "$BASELINE_FILE" > "$BASELINE_FILE.tmp" && mv "$BASELINE_FILE.tmp" "$BASELINE_FILE"
        BLOCK_COMMIT=1
    fi

    echo "------------------"
done

if [ "$BLOCK_COMMIT" -eq 1 ]; then
    echo "⛔ Commit blocked. Reduce the number of errors compared to the previous version."
    exit 1
fi

# Reset baseline after a successful commit (optional)
# jq -n '{}' > "$BASELINE_FILE"

echo "✅ [PHPStan] Check completed successfully."

exit 0
