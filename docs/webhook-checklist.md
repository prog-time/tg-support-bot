# Чек-лист: Проверка вебхука и работы бота

## 1. Предварительные требования

- [ ] Docker контейнеры запущены (`docker compose up -d`)
- [ ] Приложение доступно по публичному URL (HTTPS)
- [ ] Переменные окружения настроены в `.env`

### Необходимые переменные окружения

```env
APP_URL=https://your-domain.com

# Telegram
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_SECRET_KEY=your_secret_key
TELEGRAM_GROUP_ID=your_group_id

# VK
VK_TOKEN=your_vk_token
VK_SECRET=your_vk_secret
VK_CONFIRMATION_CODE=your_confirmation_code
```

---

## 2. Настройка Telegram вебхука

### 2.1. Установка вебхука

**Через HTTP запрос**
```
GET https://api.telegram.org/bot{{TELEGRAM_ТОКЕН}}/setWebhook?url=https://{ДОМЕН}/api/telegram/bot&max_connections=45&drop_pending_updates=true&secret_token={{СЕКРЕТНЫЙ_КЛЮЧ_ИЗ_ENV}}
```

### 2.2. Проверка статуса вебхука

```bash
curl "https://api.telegram.org/bot{{TELEGRAM_ТОКЕН}}/getWebhookInfo"
```

**Ожидаемый ответ:**
```json
{
  "ok": true,
  "result": {
    "url": "https://{ДОМЕН}/api/telegram/bot",
    "has_custom_certificate": false,
    "pending_update_count": 0,
    "max_connections": 40
  }
}
```

### 2.3. Чек-лист проверки Telegram

- [ ] `url` соответствует вашему домену
- [ ] `pending_update_count` равен 0 (нет необработанных сообщений)
- [ ] `last_error_date` отсутствует или старый

---

## 3. Настройка VK вебхука

### 3.1. Настройка в VK

1. Перейдите в настройки сообщества VK
2. Раздел **Работа с API** → **Callback API**
3. Укажите URL: `https://your-domain.com/api/vk/bot`
4. Введите секретный ключ из `.env` (VK_SECRET)
5. Подтвердите сервер (бот вернет код подтверждения)

### 3.2. Чек-лист проверки VK

- [ ] Сервер подтвержден в настройках VK
- [ ] Включены нужные типы событий (входящие сообщения)
- [ ] Статус сервера: "Работает"

---

## 4. Тестирование работы бота

### 4.1. Telegram

1. **Отправка тестового сообщения**
   - [ ] Напишите боту в личные сообщения
   - [ ] Проверьте, что сообщение появилось в группе поддержки

2. **Проверка ответа**
   - [ ] Ответьте на сообщение в группе
   - [ ] Проверьте, что пользователь получил ответ

3. **Проверка логов**
   ```bash
   docker exec -it pet tail -f storage/logs/laravel.log
   ```

### 4.2. VK

1. **Отправка тестового сообщения**
   - [ ] Напишите в сообщения сообщества
   - [ ] Проверьте пересылку в Telegram группу

2. **Проверка ответа**
   - [ ] Ответьте на сообщение в Telegram
   - [ ] Проверьте доставку ответа в VK

---

## 5. Диагностика проблем

### 5.1. Telegram не получает сообщения

```bash
# Проверка статуса вебхука
curl "https://api.telegram.org/bot{{TELEGRAM_ТОКЕН}}/getWebhookInfo"

# Удаление вебхука (для сброса)
curl "https://api.telegram.org/bot{{TELEGRAM_ТОКЕН}}/deleteWebhook"
```

Далее повторно установить хук (пункт 2.1).

### 5.2. Ошибки SSL сертификата

- [ ] Проверьте, что сертификат валидный (не самоподписанный)
- [ ] Проверьте цепочку сертификатов
- [ ] URL должен быть HTTPS

### 5.3. Проверка доступности эндпоинта

```bash
# Telegram endpoint
curl -X POST https://{{ДОМЕН}}/api/telegram/bot \
  -H "Content-Type: application/json" \
  -d '{"test": true}'

# VK endpoint
curl -X POST https://{{ДОМЕН}}/api/vk/bot \
  -H "Content-Type: application/json" \
  -d '{"type": "confirmation", "group_id": 123}'
```

### 5.4. Проверка очередей

```bash
# Статус воркера
docker exec -it pet php artisan queue:work --once

# Просмотр failed jobs
docker exec -it pet php artisan queue:failed
```

---

## 6. Полезные команды

| Команда | Описание |
|---------|----------|
| `php artisan queue:work` | Запустить обработчик очередей |
| `php artisan queue:failed` | Показать неудачные задачи |
| `php artisan queue:retry all` | Повторить все неудачные задачи |
| `php artisan config:cache` | Кэшировать конфигурацию |
| `php artisan route:list` | Список всех маршрутов |

---

## 7. Финальный чек-лист

### Telegram
- [ ] Вебхук установлен и активен
- [ ] Бот отвечает на `/start`
- [ ] Сообщения пересылаются в группу
- [ ] Ответы доставляются пользователю
- [ ] Логи без ошибок

### VK
- [ ] Callback сервер подтвержден
- [ ] Сообщения пересылаются в Telegram
- [ ] Ответы доставляются в VK
- [ ] Логи без ошибок

### Инфраструктура
- [ ] SSL сертификат валидный
- [ ] Очереди работают
- [ ] Логирование настроено
- [ ] Мониторинг ошибок активен
