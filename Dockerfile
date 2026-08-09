FROM php:8.3-fpm

# Используем bash с pipefail для всех RUN
SHELL ["/bin/bash", "-o", "pipefail", "-c"]

# Установка системных пакетов и Node.js
RUN apt-get update && \
    apt-get install -y --no-install-recommends git curl zip unzip libpq-dev libicu-dev libzip-dev shellcheck && \
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y --no-install-recommends nodejs && \
    docker-php-ext-install pdo pdo_pgsql pgsql intl zip && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Настройки PHP
COPY ./docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
# $HOME у www-data — это /var/www, то есть корень проекта. Без этой переменной
# composer, запущенный внутри контейнера, создаёт кэш в /var/www/.composer и
# засоряет рабочую копию (а под mutagen — ещё и уезжает на сервер).
ENV COMPOSER_HOME=/tmp/composer

# WORKDIR до COPY проекта
WORKDIR /var/www

# Копируем проект
COPY . .

# Очищаем кэш фреймворка до установки зависимостей
RUN rm -f bootstrap/cache/*.php

# Права доступа на storage и bootstrap/cache
RUN mkdir -p storage/logs \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache && \
    chown -R www-data:www-data storage bootstrap/cache && \
    find storage bootstrap/cache -type d -exec chmod 775 {} + && \
    find storage bootstrap/cache -type f -exec chmod 664 {} +

# Отключаем получение git commit info для Laravel/npm
ENV LARAVEL_GIT_COMMIT=false

# ВНИМАНИЕ: docker-compose.yml монтирует рабочую копию как `.:/var/www`, и этот
# bind-mount перекрывает /var/www целиком — включая vendor/, node_modules/ и
# public/build/, собранные ниже. Эти слои нужны только для запуска образа БЕЗ
# монтирования (например, продакшен-деплой самого образа). При обычной
# установке через compose то же самое (composer install / npm run build)
# выполняет docker/scripts/entrypoint.sh при первом старте контейнера `app` —
# см. его комментарий и README, «Установка».

# Установка PHP зависимостей
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Установка Node.js зависимостей и сборка фронтенда
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi && \
    npm run build

# Меняем пользователя на www-data (uid 33 — числовой ID, чтобы юзер
# резолвился и на хостах без записи www-data в /etc/passwd)
USER 33:33

EXPOSE 9000
ENTRYPOINT ["/bin/bash", "/var/www/docker/scripts/entrypoint.sh"]
CMD ["php-fpm"]
