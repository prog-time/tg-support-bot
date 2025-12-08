#!/bin/bash
set -e

# -----------------------------------------
# Configuration
# -----------------------------------------
DOCKER_SERVICE="app"
PROJECT_DIR="/home/multichat"

# Directories to check (relative to PROJECT_DIR)
DIRS=(
    "scripts"
    # "docker/scripts" # add more if needed
)
# -----------------------------------------

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
success() { echo -e "${GREEN}✅ $1${NC}"; }
warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; }

ERROR_FOUND=0

cd "$PROJECT_DIR" || { error "Cannot cd to $PROJECT_DIR"; exit 1; }

for DIR in "${DIRS[@]}"; do
    if [ ! -d "$DIR" ]; then
        warning "Directory $DIR does not exist. Skipping."
        continue
    fi

    info "Checking directory: $DIR"

    # Find all shell scripts
    sh_files=$(find "$DIR" -type f -name "*.sh")
    if [ -z "$sh_files" ]; then
        info "No .sh files found in $DIR"
        continue
    fi

    for file in $sh_files; do
        info "Checking $file..."

        # Run ShellCheck inside Docker (warnings and errors only)
        output=$(docker compose exec -T "$DOCKER_SERVICE" shellcheck --severity=warning "$file" 2>&1) || rc=$?
        if [ -n "$output" ]; then
            echo "$output"
        fi

        if [ "${rc:-0}" -ne 0 ]; then
            ERROR_FOUND=1
        fi
    done
done

if [[ $ERROR_FOUND -eq 0 ]]; then
    success "All shell scripts passed important ShellCheck checks!"
else
    error "ShellCheck found important issues!"
fi

exit $ERROR_FOUND
