# AI-помощник: как работает и как настроить

> **Аудитория:** операционная команда, DevOps, разработчики
> **Затрагивает:** конфигурацию `.env`, двух Telegram-ботов, очередь Laravel
> **Связанные документы:** [`switching-manager-interface.md`](switching-manager-interface.md), [`webhook-checklist.md`](webhook-checklist.md), [`rules/domain/ai-assistant.md`](../rules/domain/ai-assistant.md)

---

## TL;DR

- AI-помощник работает **только** в режиме `MANAGER_INTERFACE=telegram_group`.
- Триггерится из вебхука **основного** бота при каждом приватном сообщении пользователя.
- AI-бот в супергруппе — **визуальный лейбл**: он постит ответ в тред, чтобы менеджеры видели, что это AI. Он **не слушает** сообщения в группе.
- Два режима: автоответ (`AI_AUTO_REPLY=true`) и черновик с кнопками Accept/Cancel (`AI_AUTO_REPLY=false`, по умолчанию).
- Поддерживаются провайдеры: OpenAI, DeepSeek, GigaChat.

---

## Архитектура

```
┌──────────┐  private message   ┌──────────────────────┐
│ Пользова-│ ─────────────────▶ │ Основной бот         │ ── TELEGRAM_TOKEN
│   тель   │                    │ POST /api/telegram/  │
└──────────┘                    │      bot             │
     ▲                          └──────────┬───────────┘
     │                                     │ TelegramBotController
     │                                     │  → notifyIncomingMessage()  (пост в тред супергруппы как обычно)
     │                                     │  → maybeDispatchAi()        (если правила сходятся)
     │                                     ▼
     │                          ┌──────────────────────┐
     │                          │ SendAiReplyJob       │ ── AI_AUTO_REPLY=true
     │                          │   или                │
     │                          │ SendAiDraftJob       │ ── AI_AUTO_REPLY=false
     │                          └──────┬───────────────┘
     │                                 │
     │            ┌────────────────────┼────────────────────┐
     │            ▼                    ▼                    ▼
     │   ┌────────────────┐  ┌──────────────────┐  ┌─────────────────┐
     │   │ AI-провайдер   │  │ AI-бот           │  │ Основной бот    │
     │   │ OpenAI /       │  │ TELEGRAM_AI_BOT_ │  │ TELEGRAM_TOKEN  │
     │   │ DeepSeek /     │  │ TOKEN            │  │                 │
     │   │ GigaChat       │  │ → post в тред    │  │ → SendTelegram- │
     │   └────────────────┘  │   супергруппы    │  │   MessageJob    │
     │                       └──────────────────┘  │   юзеру в личку │
     │                                             └────────┬────────┘
     │                                                      │
     └──────────────────────────────────────────────────────┘
```

Параллельно отправляются **два сообщения с одинаковым текстом**:
- в супергруппу (через AI-бот) — визуально менеджерам видно, что отвечает AI;
- пользователю в личку (через основной бот) — пользователь получает обычный ответ поддержки и не знает, что есть отдельный AI-бот.

В режиме черновика (`AI_AUTO_REPLY=false`) пользователю **ничего не уходит** до клика менеджером на кнопку Accept.

---

## Роли двух ботов

| Бот | Webhook | Что слушает | Что делает |
|---|---|---|---|
| **Основной** (`TELEGRAM_TOKEN`) | `POST /api/telegram/bot` | Все апдейты (`message`, `callback_query`, `edited_message`, …) | Принимает сообщения от пользователей, постит их в супергруппу, доставляет ответы пользователям, триггерит AI |
| **AI** (`TELEGRAM_AI_BOT_TOKEN`) | `POST /api/ai-bot/webhook` | **Только** `callback_query` | Постит ответы/черновики AI в треды супергруппы, принимает клики на Accept/Cancel |

> **Важно:** AI-бот **не пытается слушать сообщения** в супергруппе. Telegram не доставляет ботам сообщения, отправленные другими ботами, поэтому такая схема не работала бы в принципе. Триггер AI всегда из основного бота.

---

## Режимы работы

### `AI_AUTO_REPLY=true` — автоответ

При каждом подходящем сообщении пользователя AI **сразу** генерирует и отправляет ответ.

**Поток:**
1. Пользователь пишет «Когда отправляете?» основному боту.
2. Основной бот публикует сообщение в треде супергруппы (как обычно).
3. `SendAiReplyJob`:
   - запрашивает AI-провайдер → получает текст;
   - публикует **тот же текст** в тред супергруппы через AI-бот (обычным сообщением, без обёртки);
   - диспатчит `SendTelegramMessageJob`, который отправляет тот же текст пользователю от имени основного бота.
4. Сохраняется запись `AiMessage`.

**Когда выбирать:** когда AI-ответы уже достаточно качественные, чтобы доверять им без модерации, или для FAQ-сценариев с простыми вопросами.

### `AI_AUTO_REPLY=false` — черновик (по умолчанию)

AI генерирует ответ, но публикует его как **черновик в треде супергруппы** с кнопками **Accept** / **Cancel**. Пользователю ничего не уходит, пока менеджер не подтвердит.

**Поток:**
1. Пользователь пишет основному боту.
2. Сообщение публикуется в треде как обычно.
3. `SendAiDraftJob`:
   - запрашивает AI;
   - публикует в тред через AI-бот сообщение вида:
     ```
     📄 Инструкция:

     🤖 Ответ от AI:
     <сгенерированный текст>
     ```
     с inline-кнопками **✅ Отправить** / **❌ Отменить**.
   - сохраняет `AiMessage` (`message_id`, `text_ai`).
4. Менеджер видит черновик и решает:
   - **Accept** → `callback_query` идёт на webhook AI-бота → `AiAcceptMessage`:
     - редактирует сообщение AI-бота в треде, убирая обёртку и кнопки;
     - диспатчит `SendTelegramMessageJob` → пользователь получает ответ от основного бота;
   - **Cancel** → `AiCancelMessage`:
     - удаляет сообщение AI-бота из треда;
     - удаляет запись из `ai_messages`;
     - пользователю ничего не уходит.

**Когда выбирать:** дефолт для большинства команд. Снижает риск некорректных автоответов, сохраняет контроль за менеджерами.

---

## Правила, при которых AI **не** срабатывает

Все проверки сосредоточены в `App\Modules\Ai\Services\ShouldAiReply::shouldGenerateForUserMessage()`. Если хоть одна не проходит, в Loki пишется `info`-лог `source: ai_should_reply_skipped` с `reason`:

| `reason` | Условие пропуска |
|---|---|
| `ai_disabled` | `AI_ENABLED=false` |
| `manager_interface_not_telegram_group` | `MANAGER_INTERFACE=admin_panel` (или иное значение) |
| `not_private_chat` | Апдейт пришёл не из приватного чата (например, сообщение менеджера в треде) |
| `not_message_query` | `callback_query`, `edited_message` и т.п. — AI реагирует только на новые сообщения |
| `empty_or_command_text` | Текст пустой или начинается с `/` (команды `/start`, `/contact`, `/ai_generate` пропускаются) |
| `user_inactive` | `BotUser` отсутствует, забанен или закрыт |

> Если AI не срабатывает на сообщение, сообщение пользователя в треде супергруппы всё равно появляется как обычно — AI просто не подключается, остальной поток работает.

---

## Настройка с нуля

### 1. Создать AI-бота в Telegram

1. Откройте [@BotFather](https://t.me/BotFather), создайте нового бота: `/newbot` → задайте имя.
2. Сохраните токен — это будет `TELEGRAM_AI_BOT_TOKEN`.
3. Сразу там же:
   - `/setprivacy` → выберите бота → **Disable** (это нужно для будущей совместимости; для текущей схемы строго не обязательно).
   - Имя/аватар бота желательно сделать так, чтобы в супергруппе было сразу понятно, что это AI (например, «AI Support», иконка робота).

### 2. Добавить AI-бота в супергруппу

В вашей supergroup-с-форумом (та же, что указана в `TELEGRAM_GROUP_ID`):
- добавьте AI-бота **участником с правами админа** (минимум: `Manage Topics`, `Send Messages`).
- без прав на топики бот не сможет публиковать в треды.

### 3. Получить креды AI-провайдера

Выберите одного из трёх:

| Провайдер | Что нужно | Env-переменные |
|---|---|---|
| **OpenAI** | API ключ ([platform.openai.com](https://platform.openai.com)) | `OPENAI_API_KEY`, `OPENAI_MODEL` (по умолчанию `gpt-3.5-turbo`) |
| **DeepSeek** | client_id + client_secret | `DEEPSEEK_CLIENT_ID`, `DEEPSEEK_CLIENT_SECRET`, `DEEPSEEK_MODEL` |
| **GigaChat** | client_id + client_secret, путь к сертификату Минцифры | `GIGACHAT_CLIENT_ID`, `GIGACHAT_CLIENT_SECRET`, `GIGACHAT_MODEL`, `GIGACHAT_CERT_PATH` |

Для GigaChat обязательно положить сертификат `russian_trusted_root_ca_pem.crt` в `certs/` и прописать путь в `GIGACHAT_CERT_PATH`.

### 4. Заполнить `.env`

```env
# Базовые
AI_ENABLED=true
AI_AUTO_REPLY=false                # начните с черновиков
AI_DEFAULT_PROVIDER=gigachat       # openai | deepseek | gigachat
MANAGER_INTERFACE=telegram_group   # AI работает только в этом режиме

# AI-бот
TELEGRAM_AI_BOT_USERNAME="@your_ai_bot"
TELEGRAM_AI_BOT_TOKEN="<token из BotFather>"
TELEGRAM_AI_BOT_SECRET="<случайная строка 16+ символов>"

# Креды провайдера (пример для GigaChat)
GIGACHAT_CLIENT_ID="..."
GIGACHAT_CLIENT_SECRET="..."
GIGACHAT_BASE_URL=https://gigachat.devices.sberbank.ru/api/v1
GIGACHAT_MODEL=GigaChat
GIGACHAT_MAX_TOKENS=1000
GIGACHAT_TEMPERATURE=0.7
GIGACHAT_CERT_PATH="certs/russian_trusted_root_ca_pem.crt"
```

`TELEGRAM_AI_BOT_SECRET` сравнивается с заголовком `X-Telegram-Bot-Api-Secret-Token` в middleware `AiBotQuery`. Сгенерировать можно любым способом (например, `openssl rand -hex 16`).

### 5. Зарегистрировать webhook AI-бота

```bash
docker exec -it pet php artisan ai-bot:set-webhook
```

Команда вызывает `setWebhook` у Telegram API с `url = APP_URL/api/ai-bot/webhook`, секретом из `.env` и `allowed_updates: ['callback_query']`. Проверить:

```bash
curl "https://api.telegram.org/bot<TELEGRAM_AI_BOT_TOKEN>/getWebhookInfo"
```

В ответе должен быть нужный `url`, `has_custom_certificate: false`, `allowed_updates: ["callback_query"]`.

### 6. Перезапустить приложение

```bash
docker exec -it pet composer dump-autoload
docker compose restart app queue
```

> Если используете `QUEUE_CONNECTION=sync`, перезапуск `queue` не критичен — Job выполняется внутри HTTP-запроса. Но `composer dump-autoload` обязателен после правки кода или изменения namespace.

### 7. Проверка

Напишите личное сообщение основному боту (например, «Здравствуйте»). В Loki должно появиться (примерный порядок):

1. лог входящего апдейта от основного бота;
2. в супергруппе в треде — ваше сообщение (обычный flow);
3. если `AI_AUTO_REPLY=true` → `source: ai_reply_sent` + сообщение от AI-бота в треде + ответ пользователю в личку;
4. если `AI_AUTO_REPLY=false` → черновик от AI-бота в треде с кнопками.

Нажмите **Accept** на черновике (если выбран `AI_AUTO_REPLY=false`) → должен прийти `source: ai_callback_accept`, и пользователь получает текст.

---

## Переключение режимов

### Включить / выключить AI

```env
AI_ENABLED=true        # включить
AI_ENABLED=false       # выключить (AI игнорирует все сообщения)
```

```bash
docker compose restart app queue
```

### Переключиться с черновиков на автоответ (и обратно)

```env
AI_AUTO_REPLY=true     # автоответ
AI_AUTO_REPLY=false    # черновик с подтверждением
```

```bash
docker compose restart app queue
```

Никаких миграций или DB-изменений не требуется.

### Сменить провайдера

```env
AI_DEFAULT_PROVIDER=openai    # или deepseek, gigachat
OPENAI_API_KEY=...
OPENAI_MODEL=gpt-4
```

```bash
docker compose restart app queue
```

Креды нужного провайдера должны быть заполнены — иначе AI-генерация упадёт, в Loki будет ошибка.

---

## Диагностика

### Точки логирования (Loki)

| `source` | Уровень | Когда |
|---|---|---|
| `ai_bot_request` | info | На webhook AI-бота пришёл апдейт (middleware пропустил) |
| `ai_bot_forbidden` | warning | Middleware отклонил webhook (неверный секрет / нет заголовка) |
| `ai_bot_dto_null` | warning | DTO не распарсился — апдейт неподдерживаемого типа |
| `ai_bot_ignored` | info | Webhook пришёл, но это не `callback_query` (игнорируем) |
| `ai_callback_accept` | info | Менеджер нажал Accept |
| `ai_callback_cancel` | info | Менеджер нажал Cancel |
| `ai_callback_unknown` | info | `callback_data` не подходит ни под один паттерн |
| `ai_should_reply_skipped` (+`reason`) | info | AI отказался реагировать на сообщение (см. таблицу выше) |
| `ai_reply_sent` | info | `SendAiReplyJob` успешно отправил автоответ |
| `send_ai_reply_error` / `send_ai_draft_error` | warning / error | Job упал; `warning` для известных кейсов (нет user-а, провайдер вернул пусто), `error` для неожиданного |

### Типовые проблемы

#### AI вообще не реагирует

1. Проверьте `AI_ENABLED=true` и `MANAGER_INTERFACE=telegram_group`.
2. В Loki поищите `source: ai_should_reply_skipped` — в `reason` будет конкретная причина (например, `manager_interface_not_telegram_group` — забыли поменять режим).
3. Убедитесь, что queue-worker подхватывает Job-ы (или `QUEUE_CONNECTION=sync` и Job выполняется в HTTP-контексте).

#### AI отвечает, но менеджеры не видят сообщение в супергруппе

1. AI-бот добавлен в супергруппу? Открыть участников треда, найти его.
2. У AI-бота есть права админа на постинг в треды (`Manage Topics`, `Send Messages`)?
3. В Loki ищите `send_ai_reply_error` — там будет ответ Telegram (например, `chat not found`, `not enough rights`).

#### Кнопки Accept/Cancel не работают

1. Webhook AI-бота зарегистрирован? `curl .../getWebhookInfo` — `url` должен быть `APP_URL/api/ai-bot/webhook`, `allowed_updates: ["callback_query"]`.
2. `TELEGRAM_AI_BOT_SECRET` в `.env` совпадает с тем, что был при регистрации webhook? Если меняли — перерегистрируйте: `php artisan ai-bot:set-webhook`.
3. В Loki ищите `ai_bot_forbidden` — если есть, секрет не совпадает.

#### Провайдер возвращает ошибку / пустой ответ

1. В Loki ищите `send_ai_reply_error` или `send_ai_draft_error` — там сообщение и stack-trace.
2. Проверьте креды провайдера в `.env`.
3. Для GigaChat — сертификат на месте по `GIGACHAT_CERT_PATH`?
4. Лимиты по rate-limit / квоте у провайдера.

---

## Что осталось вне сценария

- **Платформы кроме Telegram (VK, Max, External):** AI-помощник к ним пока не подключён. Текущий триггер сидит только в `TelegramBotController::controllerPlatformTg()`.
- **Режим `MANAGER_INTERFACE=admin_panel`:** AI отключён в этой ветке. Если потребуется — нужно добавить триггер AI и в логику веб-панели.
- **История диалога:** провайдер получает только текущее сообщение пользователя. Контекст из последних сообщений топика не подмешивается. Если нужно — расширять `AiAssistantService::processMessage()`.
