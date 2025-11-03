FROM php:8.3-fpm

USER root

# Установка системных пакетов и Node.js
RUN apt-get update && \
    apt-get install -y git curl zip unzip libpq-dev && \
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    docker-php-ext-install pdo pdo_pgsql pgsql && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Настройки PHP
COPY ./docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www

# Копируем проект
COPY . .

# Права доступа
RUN mkdir -p /var/www/storage/logs \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/framework/cache && \
    chown -R www-data:www-data /var/www/storage && \
    find /var/www/storage -type d -exec chmod 775 {} + && \
    find /var/www/storage -type f -exec chmod 664 {} +

# Установка PHP и Node зависимостей
RUN composer install --no-interaction --prefer-dist --optimize-autoloader && \
    npm ci && npm run build

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
