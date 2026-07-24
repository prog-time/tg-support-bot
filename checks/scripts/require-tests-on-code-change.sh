#!/bin/bash

echo "🔍 Checking tests for code changes..."

# Authorship-quality gate — skip during a merge commit; see no-inline-comments.sh.
if [ -f "$(git rev-parse --git-path MERGE_HEAD)" ]; then
  echo "✅ Merge commit — skipping (not new authorship)"
  exit 0
fi

SOURCE_DIR="app"
TEST_DIR="tests"

# Получаем изменённые файлы
changed_files=$(git diff --cached --name-only --diff-filter=ACM)

# Ищем изменения в исходном коде
code_changes=$(echo "$changed_files" | grep "^$SOURCE_DIR/.*\.php$")

if [ -z "$code_changes" ]; then
  echo "✅ No application code changes"
  exit 0
fi

# Проверяем наличие изменений в тестах
test_changes=$(echo "$changed_files" | grep "^$TEST_DIR/.*Test\.php$")

if [ -z "$test_changes" ]; then
  echo ""
  echo "❌ Application code changed without tests"
  echo ""
  echo "Changed files:"
  echo "$code_changes"
  echo ""
  echo "👉 Add or update tests before commit"
  exit 1
fi

echo "✅ Code changes have related tests"
