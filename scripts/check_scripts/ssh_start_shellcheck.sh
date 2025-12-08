#!/bin/bash
set -e

# -----------------------------------------
# SSH settings
# -----------------------------------------
SERVER_USER="root"
SERVER_HOST="45.80.69.244"
REMOTE_SCRIPT="/home/multichat/scripts/check_scripts/check_shell_scripts.sh"
# -----------------------------------------

# Connect to server and run the remote script
ssh "${SERVER_USER}@${SERVER_HOST}" "$REMOTE_SCRIPT"
exit_code=$?

if [[ $exit_code -eq 0 ]]; then
    echo -e "\033[0;32m✅ ShellCheck passed!\033[0m"
    exit 0
else
    echo -e "\033[0;31m❌ ShellCheck found important issues. Commit blocked!\033[0m"
    exit 1
fi
