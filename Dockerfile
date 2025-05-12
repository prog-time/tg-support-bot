FROM php:8.3-fpm

USER root

## НАСТРОЙКА СЕРВЕРА И УСТАНОВКА МОДУЛЕЙ
## ----------------------------
RUN apt-get update && apt-get install -y \
    git \
    nodejs npm \
    curl \
    zip \
    unzip \
    libpq-dev && \
    docker-php-ext-install pdo pdo_pgsql pgsql

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Копируем php.ini в контейнер
COPY ./docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Установка composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www

# Копируем файлы проекта
COPY . .

# Создание необходимых директорий и установка прав
RUN mkdir -p /var/www/storage/logs \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/framework/cache && \
    chown -R www-data:www-data /var/www/storage && \
    find /var/www/storage -type d -exec chmod 775 {} + && \
    find /var/www/storage -type f -exec chmod 664 {} +

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
