#!/bin/bash
set -e

# -----------------------------------------
# Configuration
# -----------------------------------------
PROJECT_DIR="/home/multichat"

DOCKERFILES=(
    "Dockerfile"
    "docker/node/Dockerfile"
)

# Игнорируем мелкие правила Hadolint
IGNORE_RULES="DL3008|DL3015"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# -----------------------------------------
info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
success() { echo -e "${GREEN}✅ $1${NC}"; }
warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; }
# -----------------------------------------

ERROR_FOUND=0

cd "$PROJECT_DIR" || { error "Cannot cd to $PROJECT_DIR"; exit 1; }

for FILE in "${DOCKERFILES[@]}"; do
    info "🔍 Checking $FILE ..."

    if [[ ! -f "$PROJECT_DIR/$FILE" ]]; then
        warning "File $FILE not found. Skipping."
        continue
    fi

    if [[ -n "$IGNORE_RULES" ]]; then
        output=$(hadolint "$FILE" 2>&1 | grep -vE "$IGNORE_RULES" || true)
    else
        output=$(hadolint "$FILE" 2>&1 || true)
    fi

    if [[ -n "$output" ]]; then
        error "Issues found in $FILE:"
        echo "$output"
        ERROR_FOUND=1
    else
        success "$FILE passed Hadolint checks!"
    fi
done

if [[ $ERROR_FOUND -eq 0 ]]; then
    success "All Dockerfiles passed Hadolint checks!"
else
    error "Hadolint found issues in one or more Dockerfiles!"
fi

exit $ERROR_FOUND
