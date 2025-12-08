FROM php:8.3-fpm

# Используем bash с pipefail для всех RUN
SHELL ["/bin/bash", "-o", "pipefail", "-c"]

# Установка системных пакетов и Node.js
RUN apt-get update && \
    apt-get install -y --no-install-recommends git curl zip unzip libpq-dev shellcheck && \
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y --no-install-recommends nodejs && \
    docker-php-ext-install pdo pdo_pgsql pgsql && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Настройки PHP
COPY ./docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# WORKDIR ставим до COPY проекта
WORKDIR /var/www

# Копируем проект
COPY . .

# Права доступа
RUN mkdir -p storage/logs \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache && \
    chown -R www-data:www-data storage && \
    find storage -type d -exec chmod 775 {} + && \
    find storage -type f -exec chmod 664 {} +

# Установка PHP и Node зависимостей
RUN composer install --no-interaction --prefer-dist --optimize-autoloader && \
    npm ci && npm run build

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
