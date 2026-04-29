#!/bin/sh

COMMIT_MSG_FILE=".git/COMMIT_EDITMSG"
BRANCH_NAME=$(git rev-parse --abbrev-ref HEAD)

if echo "$BRANCH_NAME" | grep -qE 'issues-[0-9]+'; then
  TASK_ID=$(echo "$BRANCH_NAME" | grep -oE 'issues-[0-9]+')

  if ! grep -q "$TASK_ID" "$COMMIT_MSG_FILE"; then
    sed -i.bak "1s/^/$TASK_ID | /" "$COMMIT_MSG_FILE"
    rm -f "$COMMIT_MSG_FILE.bak"
  fi
fi
