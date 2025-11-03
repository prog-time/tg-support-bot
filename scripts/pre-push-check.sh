#!/bin/bash

set -e

echo "🧪 Генерация Swagger"
bash scripts/check_scripts/check_swagger.sh commit
echo
