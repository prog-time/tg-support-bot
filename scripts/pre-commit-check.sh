#!/bin/bash

set -e

echo "🐳 Checking Dockerfiles with Hadolint..."
bash scripts/check_scripts/ssh_start_hadolint.sh
echo

echo "🐚 Checking shell scripts with ShellCheck..."
bash scripts/check_scripts/ssh_start_shellcheck.sh
echo

echo "🎨 Fixing code style with Pint..."
bash scripts/check_scripts/check_pint.sh commit
echo

echo "🧪 Running type checks with PHPStan..."
bash scripts/check_scripts/check_phpstan.sh commit
echo

echo "🔍 Checking for the presence of tests..."
bash scripts/check_scripts/find_test.sh commit
echo

echo "🧑🏻‍💻 Running tests..."
bash scripts/check_scripts/start_tests.sh commit
echo
