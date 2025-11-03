#!/bin/sh

COMMIT_MSG_FILE=".git/COMMIT_EDITMSG"

# Не модифицируем merge commit
if grep -qE '^Merge' "$COMMIT_MSG_FILE"; then
  exit 0
fi

# Получаем staged изменения
CHANGES=$(git diff --cached --name-status)
[ -z "$CHANGES" ] && exit 0

{
  echo ""
  echo "------------------------------"
  echo "Изменённые файлы:"

  echo "$CHANGES" | while read -r STATUS FILE1 FILE2; do
    case "$STATUS" in
      A) echo "Добавлен:   $FILE1" ;;
      M) echo "Изменён:    $FILE1" ;;
      D) echo "Удалён:     $FILE1" ;;
      R*) echo "Переименован: $FILE1 → $FILE2" ;;
      *) echo "$STATUS: $FILE1" ;;
    esac
  done
} >> "$COMMIT_MSG_FILE"
