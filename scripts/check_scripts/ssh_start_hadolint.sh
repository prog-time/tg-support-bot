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

ALL_FILES=("$@")

ssh "${SERVER_USER}@${SERVER_HOST}" \
    "cd ${PROJECT_PATH} && bash ${REMOTE_SCRIPT} ${ALL_FILES[*]}"

exit_code=$?

if [[ $exit_code -eq 0 ]]; then
    echo -e "Hadolint passed!"
    exit 0
else
    echo -e "Hadolint found issues. Commit blocked!"
    exit 1
fi
