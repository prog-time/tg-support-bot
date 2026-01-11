#!/bin/bash

# -----------------------------
# Colors
# -----------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
success() { echo -e "${GREEN}✅ $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; }

BLOCK=0

# -----------------------------
# PHPStan check
# -----------------------------
info "🔍 [PHPStan] Checking entire project..."
vendor/bin/phpstan analyse --error-format=table --no-progress
if [ $? -ne 0 ]; then
    error "⛔ Push blocked due to PHPStan errors."
    BLOCK=1
else
    success "[PHPStan] Check passed."
fi

# -----------------------------
# Laravel / Artisan tests
# -----------------------------
info "🧪 Running Laravel tests (php artisan test)..."
php artisan test --no-interaction --stop-on-failure
TEST_EXIT_CODE=$?

if [ $TEST_EXIT_CODE -ne 0 ]; then
    error "⛔ Push blocked due to failing tests."
    BLOCK=1
else
    success "All tests passed."
fi


# -----------------------------
# Block push if needed
# -----------------------------
if [ $BLOCK -eq 1 ]; then
    exit 1
fi

success "🎉 All checks passed. Push allowed."





exit 1
