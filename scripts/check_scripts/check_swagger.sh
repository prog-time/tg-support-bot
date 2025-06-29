#!/bin/bash

echo "⏳ Генерация Swagger документации..."
if ! php artisan swagger:generate; then
  echo "❌ Ошибка генерации Swagger."
  exit 1
fi

echo "✅ Swagger-документация успешно сгенерирована."
