#!/bin/sh

COMMIT_MSG_FILE=".git/COMMIT_EDITMSG"

if grep -qE '^Merge' "$COMMIT_MSG_FILE"; then
  exit 0
fi

CHANGES=$(git diff --cached --name-status)
[ -z "$CHANGES" ] && exit 0

{
  echo ""
  echo "------------------------------"
  echo "Files:"

  echo "$CHANGES" | while read -r STATUS FILE1 FILE2; do
    case "$STATUS" in
      A) echo "Added:    $FILE1" ;;
      M) echo "Changed:  $FILE1" ;;
      D) echo "Deleted:  $FILE1" ;;
      R*) echo "Renamed: $FILE1 → $FILE2" ;;
      *) echo "$STATUS:  $FILE1" ;;
    esac
  done
} >> "$COMMIT_MSG_FILE"
