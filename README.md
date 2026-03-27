# TG Support Bot — Мультиканальная платформа технической поддержки

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](./LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.0+-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![Docker](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)](https://www.docker.com/)

Телеграм бот для объединения сообщений из **Telegram**, **ВКонтакте**, **Max** и **сторонних API источников** в единую систему технической поддержки.

Сообщения отправляются в Telegram-группу, где под каждого пользователя создаётся отдельная **чат-тема (топик)**.

Бот поддерживает **все типы сообщений**: текст, изображения, файлы, голосовые сообщения, видео, стикеры, контакты и другие медиафайлы.

---

## Демонстрация

**Документация:** [https://docs.tg-support-bot.ru/](https://docs.tg-support-bot.ru/)

**Презентация работы бота:** [https://youtu.be/hIpYreHOxIk](https://youtu.be/hIpYreHOxIk)

**Инструкция по установке через Docker Compose:** [https://youtu.be/ZAtP9qJ5q9M](https://youtu.be/ZAtP9qJ5q9M)

**Telegram-группа поддержки:** [https://t.me/pt_tg_support](https://t.me/pt_tg_support)

---

## Содержание

- [Как это работает](#-как-это-работает)
- [Основные возможности](#-основные-возможности)
- [Технологический стек](#-технологический-стек)
- [Быстрый старт](#-быстрый-старт)
- [Установка и настройка](#-установка-и-настройка)
- [AI помощник](#-ai-помощник)
- [Живой чат для сайта](#-живой-чат-для-сайта)
- [API интеграция](#-api-интеграция)
- [Мониторинг и логирование](#-мониторинг-и-логирование)
- [Поддерживаемые типы сообщений](#-поддерживаемые-типы-сообщений)
- [Интерактивные клавиатуры](#-интерактивные-клавиатуры)
- [Архитектура](#-архитектура)
- [Развертывание](#-развертывание)
- [Документация](#-документация)
- [Вклад в проект](#-вклад-в-проект)
- [Лицензия](#-лицензия)

---

## Как это работает

```
┌─────────────┐         ┌─────────────┐         ┌─────────────────┐
│  Telegram   │────────▶│             │◀────────│   ВКонтакте     │
│   Users     │         │             │         │     Users       │
└─────────────┘         │             │         └─────────────────┘
                        │             │
┌─────────────┐         │   TG Bot    │         ┌─────────────────┐
│    Max      │────────▶│   Server    │◀────────│  External API   │
│   Users     │         │             │         │    Sources      │
└─────────────┘         │             │         └─────────────────┘
                        │             │
┌─────────────┐         │             │
│  Website    │────────▶│             │
│   Widget    │         │             │
└─────────────┘         └──────┬──────┘
                               │
                               ▼
                    ┌──────────────────────┐
                    │  Telegram Group      │
                    │  ┌────────────────┐  │
                    │  │ Topic: User 1  │  │
                    │  ├────────────────┤  │
                    │  │ Topic: User 2  │  │
                    │  ├────────────────┤  │
                    │  │ Topic: User 3  │  │
                    │  └────────────────┘  │
                    └──────────────────────┘
```

### Процесс обработки сообщений

1. **Получение сообщения**: Пользователь отправляет сообщение боту через Telegram, ВКонтакте, виджет сайта или внешний API
2. **Создание топика**: Бот автоматически находит или создаёт тему (топик) в Telegram-группе для этого клиента
3. **Пересылка в группу**: Сообщение пересылается в соответствующую тему с информацией об отправителе
4. **Ответ менеджера**: Менеджеры отвечают **прямо в теме** — бот отслеживает их сообщения
5. **Отправка клиенту**: Ответ автоматически пересылается клиенту **от имени бота** (без раскрытия личности менеджера)

---

## Основные возможности

### Мультиканальность
- **Telegram**: Полная поддержка Telegram Bot API
- **ВКонтакте**: Интеграция с VK API для сообщений сообщества
- **Max**: Интеграция с мессенджером Max (экосистема VK/Mail.ru)
- **Website Widget**: Готовый виджет живого чата для встраивания на сайт
- **External API**: REST API для подключения сторонних источников

### Коммуникация
- Все типы медиафайлов (текст, изображения, документы, голосовые, видео, стикеры, контакты)
- Автоматическая организация диалогов в топики Telegram-группы
- Приватность: клиенты не видят, кто из менеджеров им отвечает
- Настраиваемые шаблоны имен топиков

### Автоматизация
- **AI помощник**: Интеграция с OpenAI, DeepSeek, GigaChat для автоматических ответов
- Очереди сообщений с Laravel Queue
- Webhook обработка в реальном времени
- WebSocket поддержка через Socket.io

### Управление и мониторинг
- **Grafana**: Визуализация метрик и статистики
- **Loki**: Централизованное логирование
- **PgAdmin**: Управление базой данных
- **RedisInsight**: Мониторинг Redis
- Интеграция с Sentry для отслеживания ошибок

### Модерация
- Блокировка пользователей
- Закрытие обращений
- История всех сообщений
- Управление внешними источниками

---

## Технологический стек

**Backend:**
- Laravel 12.0+ (PHP 8.2+)
- PostgreSQL (база данных)
- Redis (кэш и очереди)
- Laravel Queue (обработка фоновых задач)

**Frontend & Real-time:**
- Node.js + Socket.io (WebSocket сервер)
- JavaScript (виджет чата)

**External APIs:**
- Telegram Bot API
- VK API
- Max Bot API
- OpenAI API / DeepSeek / GigaChat (AI)

**DevOps:**
- Docker + Docker Compose
- Nginx (веб-сервер)
- Certbot (SSL сертификаты)

**Monitoring & Logging:**
- Grafana (дашборды)
- Loki (логи)
- Promtail (сбор логов)
- Sentry (error tracking)

**Development:**
- PHPUnit (тестирование)
- PHPStan (статический анализ)
- Laravel Pint (code style)

---

## Быстрый старт

### Требования

- Docker 20.10+
- Docker Compose 2.0+
- Git

---

## Установка и настройка

### Подготовка

**Создайте Telegram бота:**
1. Напишите [@BotFather](https://t.me/BotFather)
2. Отправьте `/newbot`
3. Следуйте инструкциям и получите `TELEGRAM_TOKEN`
4. Отключите Privacy Mode: `/setprivacy` → Disable

**Создайте Telegram группу:**
1. Создайте новую группу
2. Добавьте в неё созданного бота как администратора
3. Включите Topics (темы) в настройках группы
4. Получите `TELEGRAM_GROUP_ID` (можно через бота [@getidsbot](https://t.me/getidsbot))

**Для ВКонтакте (опционально):**
1. Создайте сообщество ВКонтакте
2. Настройте API: Настройки → API → Создать ключ
3. Получите `VK_TOKEN`, `VK_CONFIRM_CODE`, `VK_SECRET_CODE`

**Для Max (опционально):**
1. Напишите боту `@metabot` в мессенджере Max
2. Отправьте `/newbot` и следуйте инструкциям
3. Получите токен бота → `MAX_TOKEN`
4. Придумайте произвольный секретный ключ → `MAX_SECRET_KEY`
5. Зарегистрируйте вебхук: подробная инструкция в [`docs/connecting-max-bot.md`](./docs/connecting-max-bot.md)

### Конфигурация .env

```env
# Основные настройки
APP_NAME="TG Support Bot"
APP_URL=https://yourdomain.com
MAIN_DOMAIN=yourdomain.com

# Telegram Bot
TELEGRAM_TOKEN="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
TELEGRAM_GROUP_ID="-1001234567890"
TELEGRAM_SECRET_KEY="your_random_secret_key"

# VK (опционально)
VK_TOKEN="your_vk_token"
VK_CONFIRM_CODE="12345678"
VK_SECRET_CODE="your_vk_secret"

# Max (опционально)
MAX_TOKEN="your_max_bot_token"
MAX_SECRET_KEY="your_max_secret_key"

# База данных
DB_CONNECTION=pgsql
DB_HOST=pgdb
DB_PORT=5432
DB_DATABASE=support_bot
DB_USERNAME=postgres
DB_PASSWORD=secure_password

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=secure_redis_password

# Шаблон имени топика
TEMPLATE_TOPIC_NAME="{first_name} {last_name} {platform}"
```

### Выберите одну из 2 инструкций

Установка на хостинг - https://github.com/prog-time/tg-support-bot/wiki/Инструкция-по-установке-бота-на-хостинг

Установка через Docker Compose - https://github.com/prog-time/tg-support-bot/wiki/Установка-через-Docker-Compose

---

## AI помощник

Бот поддерживает интеграцию с AI для автоматической генерации ответов.

### Поддерживаемые провайдеры

- **OpenAI** (GPT-4, GPT-3.5)
- **DeepSeek**
- **GigaChat** (Сбер)

### Настройка

```env
# Включить AI
AI_ENABLED=true
AI_AUTO_REPLY=false  # true для автоматических ответов

# Выбор провайдера
AI_DEFAULT_PROVIDER=openai  # или deepseek, gigachat

# OpenAI
OPENAI_API_KEY=sk-proj-...
OPENAI_MODEL=gpt-4
OPENAI_MAX_TOKENS=1000
OPENAI_TEMPERATURE=0.7

# DeepSeek
DEEPSEEK_CLIENT_SECRET=sk-...
DEEPSEEK_MODEL=deepseek-chat

# GigaChat
GIGACHAT_CLIENT_SECRET=your_secret
GIGACHAT_MODEL=GigaChat-2-Max
```

### Управление AI

AI помощник активируется через команды бота или автоматически при включении `AI_AUTO_REPLY=true`.

Бот может генерировать ответы на основе истории диалога и контекста.

---

## Живой чат для сайта

Проект включает готовый виджет живого чата для встраивания на сайт.

### Демо

[Пример работы виджета](https://tg-support-bot.ru/preview/chat)

### Установка виджета

Подробная инструкция доступна в [разделе Wiki](https://github.com/prog-time/tg-support-bot/wiki/).

**Краткая инструкция:**

1. Скопируйте код виджета из `public/chat-widget.js`
2. Вставьте перед закрывающим тегом `</body>` на вашем сайте:

```html
<script src="https://yourdomain.com/chat-widget.js"></script>
<script>
  ChatWidget.init({
    apiUrl: 'https://yourdomain.com',
    source: 'website'
  });
</script>
```

3. Все сообщения из виджета будут поступать в Telegram группу

---

## API интеграция

### REST API для сторонних источников

Бот предоставляет REST API для подключения внешних систем.

**Endpoint:** `POST /api/external/message`

**Пример запроса:**

```bash
curl -X POST https://yourdomain.com/api/external/message \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "source": "crm-system",
    "external_user_id": "user_12345",
    "first_name": "Иван",
    "last_name": "Петров",
    "message": "Здравствуйте, у меня вопрос по заказу",
    "message_type": "text"
  }'
```

**Параметры:**
- `source` — идентификатор внешнего источника
- `external_user_id` — ID пользователя в вашей системе
- `first_name`, `last_name` — имя и фамилия
- `message` — текст сообщения
- `message_type` — тип сообщения (`text`, `photo`, `document`, и т.д.)

**Ответ:**

```json
{
  "success": true,
  "message_id": 12345,
  "topic_id": 67890
}
```

Подробная документация API доступна через Swagger: `https://yourdomain.com/api/documentation`

---

## Мониторинг и логирование

### Grafana

**URL:** `https://grafana.yourdomain.com`

Логин: значение из `GRAFANA_USER`
Пароль: значение из `GRAFANA_PASSWORD`

Grafana подключена к Loki для визуализации логов и создания дашбордов.

### Loki

Централизованное хранилище логов. Доступен на `http://loki:3100`.

### PgAdmin

Веб-интерфейс для управления PostgreSQL.

**URL:** `https://pgadmin.yourdomain.com`

Логин: `PGADMIN_EMAIL`
Пароль: `PGADMIN_PASSWORD`

### RedisInsight

Мониторинг Redis.

**URL:** `http://redis:8001`

### Sentry (опционально)

Для отслеживания ошибок в production настройте:

```env
SENTRY_LARAVEL_DSN=https://...@sentry.io/...
SENTRY_TRACES_SAMPLE_RATE=0.1
```

---

## Поддерживаемые типы сообщений

### Telegram
- ✅ Текстовые сообщения
- ✅ Фото
- ✅ Документы
- ✅ Голосовые сообщения
- ✅ Видео
- ✅ Видео-кружки (video notes)
- ✅ Стикеры
- ✅ Аудио
- ✅ Контакты
- ✅ Локации
- ✅ Опросы (polls)

### ВКонтакте
- ✅ Текстовые сообщения
- ✅ Фото
- ✅ Документы
- ✅ Голосовые сообщения
- ✅ Видео
- ✅ Стикеры
- ✅ Аудио

### Website Widget
- ✅ Текстовые сообщения
- ✅ Файлы (изображения, документы)

### Max
- ✅ Текстовые сообщения
- ✅ Фото
- ✅ Документы / файлы
- ✅ Голосовые сообщения (аудио)
- ✅ Видео (пересылается как документ)
- ✅ Контакты (пересылаются как текст с именем и телефоном)
- ✅ Геопозиция (пересылается как текст с координатами и ссылкой на Google Maps)

### External API
- ✅ Все типы через API (определяется параметром `message_type`)

---

## Интерактивные клавиатуры

Бот поддерживает отправку интерактивных клавиатур пользователям через специальный синтаксис в тексте сообщения.

### Синтаксис

Кнопки добавляются в текст сообщения с помощью двойных квадратных скобок:

```
[[Текст кнопки|тип:значение]]
```

### Типы кнопок

| Тип | Синтаксис | Описание |
|-----|-----------|----------|
| URL | `[[Открыть сайт\|url:https://example.com]]` | Кнопка со ссылкой |
| Callback | `[[Назад\|callback:back]]` | Inline callback кнопка |
| Phone | `[[Отправить номер\|phone]]` | Запрос контакта пользователя |
| Text | `[[Вариант 1]]` | Текстовая кнопка (reply keyboard) |

### Примеры использования

**Inline клавиатура с URL и callback кнопками:**

```
Добрый день! Чем могу помочь?
[[Открыть сайт|url:https://example.com]]
[[Вернуться назад|callback:back]]
[[Позвать оператора|callback:operator]]
```

**Кнопки в одном ряду** (без переноса строки между ними):

```
Выберите вариант:
[[Да|callback:yes]] [[Нет|callback:no]]
```

**Reply клавиатура с запросом контакта:**

```
Для продолжения поделитесь своим номером телефона
[[Отправить номер|phone]]
```

**Текстовые кнопки:**

```
Выберите категорию:
[[Техническая поддержка]]
[[Вопрос по оплате]]
[[Другое]]
```

### Поддержка по платформам

| Платформа | Inline Keyboard | Reply Keyboard |
|-----------|-----------------|----------------|
| Telegram | ✅ | ✅ |
| ВКонтакте | ✅ | ✅ |
| External API | ✅ (через webhook) | ✅ (через webhook) |

### Примечания

- Кнопки автоматически удаляются из текста сообщения
- Inline кнопки (url, callback) имеют приоритет над reply keyboard
- Максимум 8 кнопок в одном ряду для Telegram
- Для VK кнопки конвертируются в соответствующий формат VK API

---

## Архитектура

```
┌─────────────────────────────────────────────────────────────────┐
│                         Nginx (Reverse Proxy)                   │
│                      SSL, Load Balancing                        │
└────────────┬───────────────────────────────────┬────────────────┘
             │                                   │
    ┌────────▼────────┐                 ┌───────▼────────┐
    │  Laravel App    │                 │  Node.js       │
    │  (PHP-FPM)      │◀───────────────▶│  Socket.io     │
    │                 │     Redis       │                │
    └────────┬────────┘                 └────────────────┘
             │
    ┌────────▼──────────────────────────────────────────┐
    │              Laravel Queue Worker                 │
    │         (Background job processing)               │
    └────────┬──────────────────────────────────────────┘
             │
    ┌────────▼────────┬──────────────┬──────────────────┐
    │   PostgreSQL    │    Redis     │   File Storage   │
    │   (Database)    │  (Cache/Queue│   (Public Files) │
    └─────────────────┴──────────────┴──────────────────┘
```

### Основные компоненты

**App Service**: Laravel приложение, обрабатывает HTTP запросы, webhook'и от Telegram/VK

**Queue Worker**: Обработка фоновых задач (отправка сообщений, AI обработка)

**Node.js Server**: WebSocket сервер для виджета живого чата

**PostgreSQL**: Основная база данных (пользователи, сообщения, топики)

**Redis**: Кэш, очереди, pub/sub для real-time обновлений

**Nginx**: Веб-сервер, reverse proxy, SSL termination

**Monitoring Stack**: Grafana + Loki + Promtail для мониторинга

---

## Развертывание

### SSL/HTTPS

```bash
# Установить certbot в контейнер nginx
docker exec -it nginx certbot --nginx -d yourdomain.com

# Автоматическое продление
docker exec -it nginx certbot renew --dry-run
```

---

## Документация

**Wiki**: [https://github.com/prog-time/tg-support-bot/wiki/](https://github.com/prog-time/tg-support-bot/wiki/)

**Подключение Max**: [`docs/connecting-max-bot.md`](./docs/connecting-max-bot.md)

**API Documentation**: `https://yourdomain.com/api/documentation` (Swagger)

**Telegram группа поддержки**: [https://t.me/pt_tg_support](https://t.me/pt_tg_support)

**GitHub Issues**: [https://github.com/prog-time/tg-support-bot/issues](https://github.com/prog-time/tg-support-bot/issues)

---

## Вклад в проект

Мы приветствуем вклад сообщества!

Пожалуйста, ознакомьтесь с [CONTRIBUTING.md](./CONTRIBUTING.md) перед началом работы.

**Как помочь проекту:**

- Сообщайте об ошибках через [GitHub Issues](https://github.com/prog-time/tg-support-bot/issues)
- Предлагайте новые функции
- Улучшайте документацию
- Создавайте Pull Request'ы

---

## Поддержка проекта

Если проект был вам полезен, поддержите его:

- ⭐ Поставьте звезду на GitHub
- 📢 Расскажите о проекте друзьям и коллегам
- 🤝 Внесите вклад в разработку

---

## Лицензия

Проект распространяется под лицензией **MIT**.

Подробнее: [LICENSE](./LICENSE)

---

## Контакты

**GitHub**: [https://github.com/prog-time](https://github.com/prog-time)

**Проект**: [https://github.com/prog-time/tg-support-bot](https://github.com/prog-time/tg-support-bot)

**Telegram**: [https://t.me/pt_tg_support](https://t.me/pt_tg_support)

---

Сделано с ❤️ для сообщества
