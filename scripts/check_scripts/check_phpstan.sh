#!/bin/bash

# -----------------------------
# PARAMETERS
# -----------------------------
STRICTNESS="$1"
shift 1

FILES=("$@")
BASELINE_FILE=".phpstan-error-count.json"
BLOCK_COMMIT=0

# Initialize baseline if missing
if [ ! -f "$BASELINE_FILE" ]; then
    echo "{}" > "$BASELINE_FILE"
fi

# -----------------------------
# CHECK IF FILES EXIST
# -----------------------------
if [ ${#FILES[@]} -eq 0 ]; then
    echo "[PHPStan] No PHP files to check."
    exit 0
fi

# -----------------------------
# LOOP THROUGH FILES
# -----------------------------
for FILE in "${FILES[@]}"; do
    # Skip non-PHP files
    if [[ "$FILE" != *.php ]]; then
        continue
    fi

    # Skip if file does not exist
    if [ ! -f "$FILE" ]; then
        echo "File not found, skipping: $FILE"
        continue
    fi

    echo "Checking: $FILE"

    # Count current errors
    ERR_NEW=$(vendor/bin/phpstan analyse --error-format=raw --no-progress "$FILE" 2>/dev/null | grep -c '^')
    ERR_OLD=$(jq -r --arg file "$FILE" '.[$file] // empty' "$BASELINE_FILE")

    if [ -z "$ERR_OLD" ]; then
        echo "File not checked before. It has $ERR_NEW errors."
        ERR_OLD=$ERR_NEW
    fi

    # Determine target errors
    if [ "$STRICTNESS" = "strict" ]; then
        TARGET=0
    else
        TARGET=$((ERR_OLD - 1))
        [ "$TARGET" -lt 0 ] && TARGET=0
    fi

    # Compare and report
    if [ "$ERR_NEW" -le "$TARGET" ]; then
        echo "OK: was $ERR_OLD, now $ERR_NEW"
        # Update baseline
        jq --arg file "$FILE" --argjson errors "$ERR_NEW" '.[$file] = $errors' "$BASELINE_FILE" \
            > "$BASELINE_FILE.tmp" && mv "$BASELINE_FILE.tmp" "$BASELINE_FILE"
    else
        echo "Too many errors: $ERR_NEW (must be <= $TARGET)"
        vendor/bin/phpstan analyse --no-progress --error-format=table "$FILE"
        # Keep old baseline
        jq --arg file "$FILE" --argjson errors "$ERR_OLD" '.[$file] = $errors' "$BASELINE_FILE" \
            > "$BASELINE_FILE.tmp" && mv "$BASELINE_FILE.tmp" "$BASELINE_FILE"
        BLOCK_COMMIT=1
    fi

    echo "------------------"
done

# -----------------------------
# BLOCK COMMIT IF NEEDED
# -----------------------------
if [ "$BLOCK_COMMIT" -eq 1 ]; then
    echo "Commit blocked. Reduce the number of errors according to strictness rules."
    exit 1
fi

echo "[PHPStan] Check completed successfully."
exit 0
