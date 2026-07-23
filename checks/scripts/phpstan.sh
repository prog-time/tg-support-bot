#!/bin/bash

echo "🔍 Running PHPStan..."

LEVEL=7
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

vendor/bin/phpstan analyse \
  --level=$LEVEL \
  $files

status=$?

if [ $status -ne 0 ]; then
  echo ""
  echo "❌ PHPStan failed"
  echo "👉 Fix static analysis errors before commit"
  exit 1
fi

echo "✅ PHPStan passed"
