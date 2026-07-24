#!/bin/bash

echo "🔍 Running PHPStan..."

TARGET_DIR="app"

# Проверяем наличие PHPStan
if [ ! -f "vendor/bin/phpstan" ]; then
  echo "❌ PHPStan is not installed"
  echo "👉 Run: composer require --dev phpstan/phpstan"
  exit 1
fi

# Проверяем только изменённые PHP файлы
files=$(git diff --cached --name-only --diff-filter=ACM | grep "^$TARGET_DIR/.*\.php$")

if [ -z "$files" ]; then
  echo "✅ No PHP files changed"
  exit 0
fi

# No --level override — inherit the project's own phpstan.neon (level 6),
# same standard as linting/pre-push-check.sh and CI. A hardcoded stricter
# level here would fail code that's already green everywhere else.
vendor/bin/phpstan analyse \
  $files

status=$?

if [ $status -ne 0 ]; then
  echo ""
  echo "❌ PHPStan failed"
  echo "👉 Fix static analysis errors before commit"
  exit 1
fi

echo "✅ PHPStan passed"
