#!/bin/bash

set -e

echo "🧪 [1/1] Генерация Swagger"
bash scripts/check_scripts/check_swagger.sh commit
echo
