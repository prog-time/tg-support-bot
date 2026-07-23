#!/bin/bash

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "Running checks..."

for script in "$SCRIPT_DIR"/scripts/*.sh; do
  echo "➡️ $script"
  bash "$script"
done

echo "✅ All checks passed"

