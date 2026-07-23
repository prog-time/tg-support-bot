#!/bin/bash

echo "🔍 Checking inline comments..."

# Authorship-quality gate — skip during a merge commit. The staged diff for
# a merge is everything reconciled from both parents, not just new work; it
# would otherwise flag pre-existing style from the other branch that nobody
# touched here.
if [ -f "$(git rev-parse --git-path MERGE_HEAD)" ]; then
  echo "✅ Merge commit — skipping (not new authorship)"
  exit 0
fi

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

