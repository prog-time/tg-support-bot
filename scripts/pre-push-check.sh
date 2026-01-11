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
# PHPUnit / Autotests
# -----------------------------
info "🧪 Running PHPUnit tests..."
PHPUNIT_OUTPUT=$(vendor/bin/phpunit --colors=never 2>&1)
echo "$PHPUNIT_OUTPUT"

# Check for failures or errors
if echo "$PHPUNIT_OUTPUT" | grep -E 'FAILURES!|Errors:' >/dev/null; then
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
