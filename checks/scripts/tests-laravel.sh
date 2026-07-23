#!/bin/bash

echo "🔍 Running Laravel tests..."

if [ ! -f artisan ]; then
  echo "❌ artisan not found"
  exit 1
fi

# --parallel requires brianium/paratest, which isn't installed (only listed
# as a composer suggestion) — run serially, same as linting/pre-push-check.sh
php artisan test --stop-on-failure

status=$?

if [ $status -ne 0 ]; then
  echo "❌ Tests failed"
  exit 1
fi

echo "✅ Tests passed"
