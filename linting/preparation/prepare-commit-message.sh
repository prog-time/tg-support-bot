#!/bin/sh

COMMIT_MSG_FILE=".git/COMMIT_EDITMSG"

# Check if Claude Code CLI is installed
if ! command -v claude >/dev/null 2>&1; then
  echo "Claude Code CLI not found, skipping commit message generation."
  exit 0
fi

# Get the list of staged files
STAGED_FILES=$(git diff --cached --name-only)

# Exit if there are no staged files
if [ -z "$STAGED_FILES" ]; then
  exit 0
fi

# Form the prompt for Claude
PROMPT="git-agent desc commit en $STAGED_FILES"

# Generate commit message via Claude Code CLI (non-interactive)
COMMIT_TEXT=$(printf "%s\n" "$PROMPT" | claude -p)

# Remove possible triple backticks ``` and empty lines
COMMIT_TEXT=$(echo "$COMMIT_TEXT" | sed 's/^```//; s/```$//' | sed '/^$/d')

# Exit if the generated text is empty
if [ -z "$COMMIT_TEXT" ]; then
  exit 0
fi

# Write the generated message into COMMIT_EDITMSG
echo "$COMMIT_TEXT" > "$COMMIT_MSG_FILE"
