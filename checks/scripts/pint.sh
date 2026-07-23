#!/bin/bash

echo "🔍 Running Laravel Pint..."

# Проверяем наличие Pint
if [ ! -f "vendor/bin/pint" ]; then
  echo "❌ Laravel Pint is not installed"
  echo "👉 Run: composer require laravel/pint --dev"
  exit 1
fi

# Auto-fix code style across the repo, then re-stage only the files that
# were already part of this commit. Pint may reformat files outside the
# staged set too (e.g. something left dirty from earlier) — those get fixed
# on disk but must NOT be silently pulled into this commit.
staged_files=$(git diff --cached --name-only --diff-filter=ACM)

vendor/bin/pint

if [ -n "$staged_files" ]; then
  echo "$staged_files" | xargs -I{} git add {}
fi

echo "✅ Pint passed (auto-fixed if needed)"
