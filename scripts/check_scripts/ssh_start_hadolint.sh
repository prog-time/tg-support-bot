#!/bin/bash
set -e

# -----------------------------------------
# SSH settings
# -----------------------------------------
SERVER_USER="root"
SERVER_HOST="45.80.69.244"
PROJECT_PATH="/home/multichat"
REMOTE_SCRIPT="./scripts/check_scripts/check_hadolint.sh"
# -----------------------------------------

ssh "${SERVER_USER}@${SERVER_HOST}" "cd ${PROJECT_PATH} && bash ${REMOTE_SCRIPT}"
exit_code=$?

if [[ $exit_code -eq 0 ]]; then
    echo -e "\033[0;32m✅ Hadolint passed!\033[0m"
    exit 0
else
    echo -e "\033[0;31m❌ Hadolint found issues. Commit blocked!\033[0m"
    exit 1
fi
