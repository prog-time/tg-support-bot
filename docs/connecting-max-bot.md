# Подключение бота Max (VK Max)

> **Аудитория:** разработчик, DevOps
> **Требует:** публичный HTTPS-домен, токен бота Max

---

## Что такое Max

Max (ранее ICQ New) — мессенджер экосистемы VK/Mail.ru. Модуль `app/Modules/Max/` принимает входящие сообщения через вебхук и пересылает их в Telegram-группу поддержки. Ответы менеджеров доставляются пользователю обратно в Max.

Эндпоинт вебхука: `POST /api/max/bot`

---

## 1. Создание бота в Max

1. Откройте мессенджер Max (web: [max.ru](https://max.ru) или мобильное приложение)
2. Найдите бота **@metabot** (официальный BotFather для Max)
3. Отправьте `/newbot` и следуйте инструкциям:
   - Введите имя бота
   - Введите username бота (должен оканчиваться на `bot`)
4. Скопируйте полученный **токен** — он понадобится для `MAX_TOKEN`

---

## 2. Настройка переменных окружения

Откройте `.env` на сервере и добавьте:

```env
MAX_TOKEN="ваш_токен_бота"
MAX_SECRET_KEY="придумайте_произвольный_секретный_ключ"
```

> `MAX_SECRET_KEY` — произвольная строка для верификации запросов от Max.
> Max будет передавать её в заголовке `X-Max-Bot-Api-Secret` при каждом вебхук-запросе.

Перезапустите контейнер, чтобы конфиг применился:

```bash
docker compose restart app
```

---

## 3. Регистрация вебхука

Max не имеет веб-интерфейса для регистрации вебхука. Регистрация выполняется через API с помощью токена бота.

### 3.1. Через curl

```bash
curl -X POST "https://platform-api.max.ru/subscriptions" \
  -H "Authorization: Bearer ВАШ_MAX_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://ВАШ_ДОМЕН/api/max/bot",
    "update_types": ["message_created", "bot_started"],
    "secret": "ВАШ_MAX_SECRET_KEY"
  }'
```

**Ожидаемый ответ:**
```json
{ "success": true }
```

### 3.2. Через Artisan Tinker

```bash
docker exec -it pet php artisan tinker
```

```php
use MaxBotApi\Config;
use MaxBotApi\MaxClient;

$client = new MaxClient(new Config(config('traffic_source.settings.max.token')));
$result = $client->subscriptions->subscribe(
    url: config('app.url') . '/api/max/bot',
    updateTypes: ['message_created'],
    secret: config('traffic_source.settings.max.secret_key'),
);
echo $result ? 'OK' : 'FAILED';
```

### 3.3. Допустимые порты для вебхука

Max принимает вебхуки только на следующих портах:

| Порты |
|---|
| 80, 443 |
| 8080, 8443 |
| 16384–32383 |

---

## 4. Проверка регистрации вебхука

```bash
curl "https://platform-api.max.ru/subscriptions" \
  -H "Authorization: Bearer ВАШ_MAX_TOKEN"
```

**Ожидаемый ответ:**
```json
{
  "subscriptions": [
    {
      "url": "https://ваш-домен.com/api/max/bot",
      "time": 1700000000000,
      "update_types": ["message_created", "bot_started"]
    }
  ]
}
```

---

## 5. Проверка эндпоинта вручную

```bash
curl -X POST https://ВАШ_ДОМЕН/api/max/bot \
  -H "Content-Type: application/json" \
  -H "X-Max-Bot-Api-Secret: ВАШ_MAX_SECRET_KEY" \
  -d '{
    "update_type": "message_created",
    "timestamp": 1700000000000,
    "message": {
      "sender": { "user_id": 123456789, "name": "Test User" },
      "recipient": { "user_id": 123456789 },
      "timestamp": 1700000000000,
      "body": {
        "mid": "msg-test-001",
        "seq": 1,
        "text": "Тестовое сообщение"
      }
    }
  }'
```

**Ожидаемый ответ:** `ok` (HTTP 200)

**При неверном секрете:** HTTP 403:
```json
{ "message": "Access is forbidden", "error": "Secret-Key is invalid!" }
```

---

## 6. Полный чек-лист подключения

- [ ] Бот создан через @metabot, токен получен
- [ ] `MAX_TOKEN` задан в `.env`
- [ ] `MAX_SECRET_KEY` задан в `.env`
- [ ] Контейнер перезапущен (`docker compose restart app`)
- [ ] Вебхук зарегистрирован через API (шаг 3)
- [ ] Регистрация вебхука подтверждена (шаг 4)
- [ ] Ручной тест эндпоинта возвращает `ok` (шаг 5)
- [ ] Тестовое сообщение от пользователя Max появляется в Telegram-группе
- [ ] Ответ менеджера из Telegram доставляется в Max

---

## 7. Диагностика проблем

### 7.1. Вебхук не регистрируется

- Убедитесь, что домен доступен извне и работает по HTTPS
- Проверьте, что порт входит в список допустимых (80, 443, 8080, 8443, 16384–32383)
- Проверьте корректность токена в `MAX_TOKEN`

### 7.2. Эндпоинт возвращает 403

- Убедитесь, что `MAX_SECRET_KEY` в `.env` совпадает со значением `secret` при регистрации вебхука
- Убедитесь, что в curl-запросе передаётся заголовок `X-Max-Bot-Api-Secret` с правильным значением

### 7.3. Сообщение не появляется в Telegram-группе

Проверьте логи приложения:

```bash
docker exec -it pet tail -f storage/logs/laravel.log
```

Или логи Loki (если настроен Grafana):

```bash
docker exec -it pet php artisan queue:work --once
```

Убедитесь, что очередь работает:

```bash
docker exec -it pet php artisan queue:work
```

### 7.4. Удаление вебхука (сброс)

```bash
curl -X DELETE "https://platform-api.max.ru/subscriptions" \
  -H "Authorization: Bearer ВАШ_MAX_TOKEN"
```

После сброса повторно зарегистрируйте вебхук (шаг 3).

---

## 8. Архитектурная справка

| Компонент | Файл |
|---|---|
| Маршрут | `app/Modules/Max/routes.php` → `POST /api/max/bot` |
| Аутентификация | `app/Modules/Max/Middleware/MaxQuery.php` |
| Контроллер | `app/Modules/Max/Controllers/MaxBotController.php` |
| Обработка входящих | `app/Modules/Max/Services/MaxMessageService.php` |
| Пересылка в Telegram | `app/Modules/Telegram/Jobs/SendMaxTelegramMessageJob.php` |
| Ответ в Max | `app/Modules/Max/Jobs/SendMaxMessageJob.php` |
| API-обёртка SDK | `app/Modules/Max/Api/MaxMethods.php` |
| Конфиг | `config/traffic_source.php` → `settings.max` |

---

## Ссылки

- SDK: [prog-time/max-php-sdk](https://github.com/prog-time/max-php-sdk)
- Max Bot API: [dev.max.ru](https://dev.max.ru)
- Чек-лист вебхуков: `docs/webhook-checklist.md`
