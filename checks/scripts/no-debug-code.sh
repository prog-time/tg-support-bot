#!/bin/bash

echo "🔍 Checking debug code..."

TARGET_DIR="app"

PATTERNS=(
  "dd("
  "dump("
  "var_dump("
  "ray("
  "console.log("
  "console.debug("
)

fail=0

files=$(git diff --cached --name-only --diff-filter=ACM)

for file in $files; do

  if [[ "$file" != "$TARGET_DIR/"* ]]; then
    continue
  fi

  if [[ ! -f "$file" ]]; then
    continue
  fi

  for pattern in "${PATTERNS[@]}"; do

    if grep -n "$pattern" "$file" > /dev/null; then
      echo "❌ Debug code found: $file"
      grep -n "$pattern" "$file"
      fail=1
    fi

  done

done

if [ $fail -ne 0 ]; then
  echo ""
  echo "👉 Remove debug code before commit"
  exit 1
fi

echo "✅ No debug code found"
