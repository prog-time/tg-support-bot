#!/bin/bash

set -e

# -----------------------------------------
# SSH + DOCKER SETTINGS
# -----------------------------------------
SERVER_USER="root"
SERVER_HOST="45.80.69.244"
PROJECT_DIR="/home/multichat"  # path on server
DOCKER_SERVICE="app"           # Docker service name
# -----------------------------------------

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
success() { echo -e "${GREEN}✅ $1${NC}"; }
warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; }

# -----------------------------------------
# Directories to check (relative to PROJECT_DIR)
# -----------------------------------------
DIRS=(
    "scripts"
)

# -----------------------------------------
# Run ShellCheck on server inside Docker
# -----------------------------------------
run_shellcheck() {
    local error_found=0

    # Connect via SSH
    ssh ${SERVER_USER}@${SERVER_HOST} bash << EOF
cd "$PROJECT_DIR"

for DIR in "${DIRS[@]}"; do
    if [ ! -d "\$DIR" ]; then
        echo -e "${YELLOW}⚠️ Directory \$DIR does not exist. Skipping.${NC}"
        continue
    fi

    echo -e "${BLUE}ℹ️ Checking directory: \$DIR${NC}"

    # Find all .sh files recursively
    sh_files=\$(find "\$DIR" -type f -name "*.sh")
    if [ -z "\$sh_files" ]; then
        echo -e "${BLUE}ℹ️ No .sh files found in \$DIR${NC}"
        continue
    fi

    # Check each file inside Docker
    for file in \$sh_files; do
        echo -e "${BLUE}ℹ️ Checking \$file...${NC}"
        docker compose exec -T $DOCKER_SERVICE shellcheck "\$file"
        if [ \$? -ne 0 ]; then
            exit_code=1
        fi
    done
done
EOF

    return $?
}

# -----------------------------------------
# Main function
# -----------------------------------------
main() {
    info "🐚 Running ShellCheck on server via Docker..."
    run_shellcheck
    local exit_code=$?

    if [[ $exit_code -eq 0 ]]; then
        success "All shell scripts passed ShellCheck on server!"
        exit 0
    else
        error "ShellCheck found errors in shell scripts on server."
        exit 1
    fi
}

main "$@"
