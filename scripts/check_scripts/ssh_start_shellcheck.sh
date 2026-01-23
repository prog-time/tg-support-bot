#!/bin/bash
set -e

# -----------------------------------------
# SSH settings
# -----------------------------------------
SERVER_USER="root"
SERVER_HOST="45.80.69.244"
REMOTE_SCRIPT="/home/multichat/scripts/check_scripts/check_shellcheck.sh"
# -----------------------------------------

ALL_FILES=("$@")

# Connect to server and run the remote script
ssh "${SERVER_USER}@${SERVER_HOST}" "${REMOTE_SCRIPT} ${ALL_FILES[*]}"
exit_code=$?

if [[ $exit_code -eq 0 ]]; then
    echo -e "ShellCheck passed!\033[0m"
    exit 0
else
    echo -e "ShellCheck found important issues. Commit blocked!\033[0m"
    exit 1
fi
