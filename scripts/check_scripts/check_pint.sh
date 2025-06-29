#!/bin/bash

COMMAND="$1"  # commit или push

if [ "$COMMAND" = "commit" ]; then
    ALL_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)
elif [ "$COMMAND" = "push" ]; then
    BRANCH=$(git rev-parse --abbrev-ref HEAD)
    ALL_FILES=$(git diff --name-only origin/$BRANCH --diff-filter=ACM | grep '\.php$' || true)
else
    echo "Неизвестная команда: $COMMAND"
    exit 1
fi


if [ -z "$ALL_FILES" ]; then
  echo "✅ [Pint] Нет PHP-файлов для проверки."
  exit 0
fi

echo "🔍 [Pint] Проверка code style..."

vendor/bin/pint --test $ALL_FILES

RESULT=$?

if [ $RESULT -ne 0 ]; then
  echo "❌ Pint нашёл ошибки. Автоисправление..."
  vendor/bin/pint $ALL_FILES
  echo "$ALL_FILES" | xargs git add
  echo "✅ [Pint] Code style исправлен. Перезапусти коммит."
  exit 1
fi

echo "✅ [Pint] Всё чисто."
exit 0
