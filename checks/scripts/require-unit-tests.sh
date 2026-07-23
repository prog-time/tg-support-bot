#!/bin/bash

echo "🔍 Checking unit tests for changed classes..."

EXCLUDE_PATTERNS=(
    "Controller"
    "Model"
    "DTO"
    "Request"
    "Resource"
    "Migration"
    "Seeder"
    "Exception"
)

fail=0


should_skip() {
    local file="$1"

    for pattern in "${EXCLUDE_PATTERNS[@]}"; do
        if [[ "$file" == *"$pattern"* ]]; then
            return 0
        fi
    done

    return 1
}


files=$(git diff --cached --name-only --diff-filter=ACM | grep "^app/.*\.php$")


for file in $files; do

    if should_skip "$file"; then
        continue
    fi


    test_file=$(echo "$file" \
        | sed 's#^app/#tests/Unit/#' \
        | sed 's/\.php$/Test.php/')


    if [[ ! -f "$test_file" ]]; then
        echo "❌ Missing test:"
        echo "   Class: $file"
        echo "   Expected: $test_file"
        fail=1
    fi

done


if [[ $fail -eq 1 ]]; then
    echo ""
    echo "👉 Add unit tests for changed classes"
    exit 1
fi


echo "✅ Unit tests exist"
