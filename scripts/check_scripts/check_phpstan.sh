#!/bin/bash

# ПРОВЕРЯЕМ НОВЫЕ ФАЙЛЫ

COMMAND="$1"  # commit или push

if [ "$COMMAND" = "commit" ]; then
    # только новые файлы (статус A = Added)
    NEW_FILES=$(git diff --cached --name-only --diff-filter=A | grep '\.php$')

    if [ -z "$NEW_FILES" ]; then
        echo "✅ Нет новых PHP-файлов. Пропускаем проверку PHPStan для новых файлов."
    else
        echo "🔍 Проверка PHPStan только для новых файлов..."
        ./vendor/bin/phpstan analyse --no-progress --error-format=table $NEW_FILES
        if [ $? -ne 0 ]; then
          echo "❌ НОВЫЕ ФАЙЛЫ! PHPStan нашёл ошибки типизации (ОБЯЗАТЕЛЬНО)"
          exit 1
        fi
    fi
fi


# ===============

# ПРОВЕРЯЕМ ИЗМЕНЕННЫЕ ФАЙЛЫ

BASELINE_FILE=".phpstan-error-count.json"
BLOCK_COMMIT=0

if [ "$COMMAND" = "commit" ]; then
    ALL_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)
elif [ "$COMMAND" = "push" ]; then
    BRANCH=$(git rev-parse --abbrev-ref HEAD)
    ALL_FILES=$(git diff --name-only origin/$BRANCH --diff-filter=ACM | grep '\.php$' || true)
else
    echo "Неизвестная команда: $COMMAND"
    exit 1
fi

if [ ! -f "$BASELINE_FILE" ]; then
    echo "{}" > "$BASELINE_FILE"
fi

if [ -z "$ALL_FILES" ]; then
  echo "✅ [PHPStan] Нет PHP-файлов для проверки."
  exit 0
fi

echo "🔍 [PHPStan] Проверка файлов"

for FILE in $ALL_FILES; do
    echo "📄 Проверка: $FILE"

    # Получаем версию файла из HEAD
    TMP_BEFORE=$(mktemp)
    ERR_OLD=$(jq -r --arg file "$FILE" '.[$file] // 0' "$BASELINE_FILE")
    rm "$TMP_BEFORE"

    # Текущая версия файла (staged-контент должен совпадать с рабочим каталогом)
    ERR_NEW=$(vendor/bin/phpstan analyse --error-format=raw --no-progress "$FILE" 2>/dev/null | grep -c '^')

    # условие проверки
    TARGET=$((ERR_OLD - 1))
    if [ "$TARGET" -lt 0 ]; then
        TARGET=0
    fi

    if [ "$ERR_OLD" -eq 0 ] && [ "$ERR_NEW" -eq 0 ]; then
        echo "✅ Новый файл. Без ошибок."
    elif [ "$ERR_NEW" -le "$TARGET" ]; then
        echo "✅ Улучшено: было $ERR_OLD, стало $ERR_NEW"
        jq --arg file "$FILE" --argjson errors "$ERR_NEW" '.[$file] = $errors' "$BASELINE_FILE" > "$BASELINE_FILE.tmp" && mv "$BASELINE_FILE.tmp" "$BASELINE_FILE"
    else
        echo "❌ Ошибок: $ERR_NEW (нужно ≤ $TARGET)"
        vendor/bin/phpstan analyse --no-progress --error-format=table "$FILE"
        BLOCK_COMMIT=1
    fi

    echo "------------------"
done

if [ "$BLOCK_COMMIT" -eq 1 ]; then
    echo "⛔ Коммит остановлен. Уменьши количество ошибок по сравнению с предыдущей версией."
    exit 1
fi

# Обнуляем baseline после успешного коммита
#jq -n '{}' > "$BASELINE_FILE"

echo "✅ [PHPStan] Проверка завершена успешно."

# ===============

exit 0
