#!/bin/bash

echo "⏳ Generate Swagger"
if ! php artisan swagger:generate; then
  echo "❌ Error generate Swagger"
  exit 1
fi

echo "✅ Swagger documentation has been successfully generated"
