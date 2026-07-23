#!/bin/bash

echo "🔍 Checking inline comments..."

TARGET_DIR="app"

fail=0

files=$(git diff --cached --name-only --diff-filter=ACM)

for file in $files; do

  if [[ "$file" != "$TARGET_DIR/"* ]]; then
    continue
  fi

  if [[ ! -f "$file" ]]; then
    continue
  fi

  # Ищем PHP комментарии //
  if grep -nE '^[[:space:]]*//' "$file"; then
    echo ""
    echo "❌ Inline comment found: $file"
    fail=1
  fi

  # Ищем комментарии /* кроме PHPDoc /** 
  if grep -nE '^[[:space:]]*/\*(?!\*)' "$file" 2>/dev/null; then
    echo ""
    echo "❌ Block comment found: $file"
    fail=1
  fi

done

if [ $fail -ne 0 ]; then
  echo ""
  echo "👉 Remove unnecessary comments. Code should explain itself."
  exit 1
fi

echo "✅ No inline comments found"

