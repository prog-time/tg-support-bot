#!/bin/bash

echo "🔍 Checking branch name..."

branch=$(git branch --show-current)

pattern="issues-[0-9]+"

if [[ ! $branch =~ $pattern ]]; then
  echo "❌ Invalid branch name: $branch"
  echo "👉 Branch must contain issue id (e.g. issues-123)"
  exit 1
fi

echo "✅ Branch name OK: $branch"
