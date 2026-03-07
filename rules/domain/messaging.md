# Messaging Domain

> **Purpose:** This file defines business rules, state machines, and invariants for the core messaging domain — the routing of messages between users (Telegram, VK, External) and the support team.
> **Context:** Read this file before modifying anything related to message sending, editing, routing, or platform integrations.
> **Version:** 1.0

---

## 1. What is this domain?

The Messaging domain is responsible for receiving, routing, storing, and forwarding messages between end users (on Telegram, VK, or External platforms) and the support team (working in a Telegram supergroup with forum topics).

This domain owns: message creation, message routing, platform-specific sending logic, file handling, keyboard construction.

This domain does not own: user banning (see `domain/bot-users.md`), AI response generation (see `domain/ai-assistant.md`), external source registration (see `domain/external-sources.md`).

---

## 2. Key Concepts

| Concept | Description |
|---|---|
| Forum Topic | A dedicated thread in a Telegram supergroup for each user's conversation |
| Incoming Message | A message sent by the user to the bot |
| Outgoing Message | A message sent by the support team to the user |
| Platform | Source of a message: `telegram`, `vk`, `external_source` |
| Job | Asynchronous queue task that performs the actual API send |
| Webhook | HTTP callback sent to an External Source when the team replies |
| Button | Interactive element attached to a message (callback, URL, phone, text) |

---

## 3. Architecture Flow

```mermaid
flowchart LR
    TelegramUser -->|webhook POST| TelegramBotController
    VkUser -->|webhook POST| VkBotController
    ExternalUser -->|REST POST| ExternalTrafficController

    TelegramBotController --> DTO[TelegramUpdateDto]
    VkBotController --> DTO2[VkUpdateDto]
    ExternalTrafficController --> DTO3[ExternalMessageDto]

    DTO --> Services[Services Layer]
    DTO2 --> Services
    DTO3 --> Services

    Services --> Jobs[Queue Jobs]
    Jobs --> TelegramAPI
    Jobs --> VkAPI
    Jobs --> WebhookService
```

---

## 4. Business Rules

**BR-001** — A message from a user must always be associated with a `BotUser` record.
_Enforced in:_ `app/Models/BotUser.php @ getOrCreateByTelegramUpdate()`, `getOrCreateExternalBotUser()`

**BR-002** — Every sent message must be recorded in the `messages` table with `bot_user_id`, `platform`, `message_type`, `from_id`, `to_id`.
_Enforced in:_ `app/Jobs/SendMessage/AbstractSendMessageJob.php @ saveMessage()`

**BR-003** — A user with `is_banned = true` must not receive replies and must receive a banned notification instead.
_Enforced in:_ `app/Actions/Telegram/SendBannedMessage.php`, `app/Actions/Vk/SendBannedMessageVk.php`

**BR-004** — All message sending to external APIs must go through queue Jobs, never synchronously from Controllers.
_Enforced in:_ `app/Http/Controllers/TelegramBotController.php`, all controllers dispatch Jobs

**BR-005** — The support team communicates with users only through a Telegram supergroup with forum topics. Each user has exactly one topic.
_Enforced in:_ `app/Jobs/TopicCreateJob.php`, `app/Models/BotUser.php @ topic_id`

**BR-006** — If a forum topic does not exist when sending a message, it must be created before sending.
_Enforced in:_ `app/Jobs/TopicCreateJob.php`

**BR-007** — File messages must be proxied through the app's own storage. Direct Telegram file URLs must not be sent to external systems.
_Enforced in:_ `app/Services/File/FileService.php`

**BR-008** — Message editing must be routed to the correct platform using the original message's platform field.
_Enforced in:_ Services `TgEditService`, `TgExternalEditService`, `TgVkEditService`, `VkEditService`

**BR-009** — Buttons attached to messages must be parsed from text and constructed into platform-specific keyboard formats.
_Enforced in:_ `app/Services/Button/KeyboardBuilder.php`, `app/Services/Button/ButtonParser.php`

**BR-010** — External source messages delivered via REST API must trigger a webhook notification to the source's `webhook_url` when the team replies.
_Enforced in:_ `app/Jobs/SendMessage/SendWebhookMessage.php`, `app/Services/Webhook/WebhookService.php`

---

## 5. Message Type State Machine

```mermaid
stateDiagram-v2
    [*] --> incoming: User sends message
    incoming --> stored: Saved to DB (messages table)
    stored --> routed: Service determines target platform
    routed --> queued: Job dispatched
    queued --> sent: API call succeeds
    queued --> failed: API call fails (retry up to 5 times)
    failed --> queued: Auto-retry with backoff
    sent --> outgoing: Manager reply recorded
    outgoing --> [*]
```

---

## 6. Platform-Specific Routing

| Inbound Platform | Reply Platform | Service | Job |
|---|---|---|---|
| `telegram` | Telegram | `TgMessageService` | `SendTelegramMessageJob` |
| `vk` | VK + Telegram mirror | `TgVkMessageService`, `VkMessageService` | `SendVkMessageJob`, `SendVkTelegramMessageJob` |
| `external_source` | Telegram + Webhook | `TgExternalMessageService` | `SendExternalTelegramMessageJob`, `SendWebhookMessage` |

---

## 7. Job Retry Rules

| Job | Max Tries | Timeout | Backoff |
|---|---|---|---|
| `SendTelegramMessageJob` | 5 | 20s | — |
| `TopicCreateJob` | 3 | — | [60, 180, 300]s |
| `SendVkMessageJob` | default | — | — |
| `SendWebhookMessage` | default | — | — |

- Jobs must handle `TelegramError::TOO_MANY_REQUESTS` by respecting `retry_after` from the API response.
- Jobs must handle `TelegramError::TOPIC_NOT_FOUND` by recreating the topic.

---

## 8. File Handling Rules

- Files are downloaded from Telegram and stored locally in `storage/app/`
- Files are served via `FilesController` (`GET /api/files/{file_id}`)
- File metadata (file_id, file_type, file_name) is stored in `external_messages` table
- Supported file types: photo, document, audio, video, voice

---

## 9. Button Rules

```php
// ✅ Correct — use ButtonParser to extract buttons from text
$parsed = ButtonParser::parse($text);

// ✅ Correct — use KeyboardBuilder to build platform keyboards
$keyboard = KeyboardBuilder::build($buttons, $platform);
```

```php
// ❌ Incorrect — manually constructing raw keyboard arrays in controller
$keyboard = ['inline_keyboard' => [[['text' => 'Yes', 'callback_data' => 'yes']]]];
```

**ButtonType enum values:**
- `callback` — inline button, triggers callback_query
- `url` — inline button, opens URL
- `phone` — reply keyboard, requests phone number
- `text` — reply keyboard, sends text

---

## 10. Forbidden Behaviors

- ❌ Sending messages synchronously from Controllers
- ❌ Calling Telegram/VK API directly from Controllers or Services (must go via `TelegramMethods` / `VkMethods`)
- ❌ Saving messages without `bot_user_id`
- ❌ Sending messages to banned users without the banned notification flow
- ❌ Creating a new forum topic without checking if one already exists
- ❌ Modifying `messages` table without updating related `external_messages` record

---

## Checklist

- [ ] Overview written
- [ ] Key concepts defined
- [ ] All business rules documented and numbered
- [ ] Enforcement locations listed
- [ ] State machine documented
- [ ] Platform routing table present
- [ ] File handling rules documented
- [ ] Button rules documented
- [ ] No forbidden behaviors
