#!/bin/bash

echo "🔍 Checking branch name..."

branch=$(git branch --show-current)

# main/dev take direct commits too — merges that resolve a PR conflict, or a
# release-only change — and have no single issue id of their own.
allowed_branches=("main" "dev")
pattern="issues-[0-9]+"

for allowed in "${allowed_branches[@]}"; do
  if [[ "$branch" == "$allowed" ]]; then
    echo "✅ Branch name OK: $branch (integration branch)"
    exit 0
  fi
done

if [[ ! $branch =~ $pattern ]]; then
  echo "❌ Invalid branch name: $branch"
  echo "👉 Branch must contain issue id (e.g. issues-123), or be main/dev"
  exit 1
fi

echo "✅ Branch name OK: $branch"
