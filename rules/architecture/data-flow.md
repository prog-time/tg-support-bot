# Data Flow

> **Version:** 1.0.0
> **Context:** Read this file to understand how data moves through the system, from webhook to delivery and back.

---

## 1. Incoming message lifecycle (Telegram → Support Group)

```mermaid
sequenceDiagram
    participant TG as Telegram
    participant MW as TelegramQuery Middleware
    participant C as TelegramBotController
    participant DTO as TelegramUpdateDto
    participant S as TgMessageService
    participant BU as BotUser Model
    participant MSG as Message Model
    participant J as SendTelegramMessageJob
    participant API as Telegram Bot API

    TG->>MW: POST /api/telegram/bot (Update JSON)
    MW->>MW: Validate X-Telegram-Bot-Api-Secret-Token
    MW->>C: Forward request
    C->>DTO: TelegramUpdateDto::fromRequest($request)
    C->>S: new TgMessageService($dto)
    S->>BU: BotUser::getOrCreateByTelegramUpdate($dto)
    S->>S: Build TGTextMessageDto
    S->>MSG: Message::create(...)
    S->>J: SendTelegramMessageJob::dispatch(botUserId, dto)
    J->>API: TelegramMethods::sendQueryTelegram('sendMessage', params)
    API-->>J: TelegramAnswerDto
    J->>MSG: Save message_id from response
```

---

## 2. Outgoing message lifecycle (Support Group → End User)

```mermaid
sequenceDiagram
    participant OP as Operator (Telegram Group)
    participant TG as Telegram
    participant C as TelegramBotController
    participant S as ToTgMessageService / TgVkMessageService / TgExternalMessageService
    participant J as SendTelegramMessageJob / SendVkMessageJob / SendWebhookMessage
    participant PLATFORM as End User Platform

    OP->>TG: Replies in forum topic
    TG->>C: POST /api/telegram/bot (typeSource=supergroup)
    C->>C: Determine BotUser.platform
    C->>S: Route to platform-specific service
    S->>J: Dispatch job for target platform
    J->>PLATFORM: Deliver message to user
```

---

## 3. External API message lifecycle

```mermaid
sequenceDiagram
    participant EXT as External System
    participant MW as ApiQuery Middleware
    participant C as ExternalTrafficController
    participant SVC as ExternalTrafficService
    participant BU as BotUser Model
    participant DB as Database
    participant J as SendExternalTelegramMessageJob
    participant TG as Telegram API

    EXT->>MW: POST /api/external/{id}/messages/ (Bearer token)
    MW->>DB: Validate token in external_source_access_tokens
    MW->>C: Forward request
    C->>SVC: ExternalTrafficService::store()
    SVC->>BU: BotUser::getOrCreateExternalBotUser(dto)
    SVC->>DB: INSERT messages + external_messages
    SVC->>J: SendExternalTelegramMessageJob::dispatch(...)
    J->>TG: Send message to support group topic
```

---

## 4. Async flow (Job queue)

All Jobs implement `ShouldQueue` and are processed by the Laravel queue worker.

```mermaid
flowchart LR
    S[Service] -- dispatch --> Q[Queue]
    Q --> W[Queue Worker]
    W -- handle --> J[Job]
    J -- success --> DB[Save to DB]
    J -- 429 --> DELAY[Delay & retry]
    J -- 403 --> BAN[BanMessage Action]
    J -- TOPIC_NOT_FOUND --> RECREATE[TopicCreateJob]
    J -- max retries exceeded --> FAILED[failed_jobs table]
```

**Job retry configuration (AbstractSendMessageJob defaults):**
- `$tries = 5` — maximum 5 attempts
- `$timeout = 20` — 20 seconds timeout per attempt
- On 429: delay is set from the `retry_after` value in the Telegram response

---

## 5. Data transformation points

| Point | Input | Transformation | Output |
|---|---|---|---|
| `TelegramUpdateDto::fromRequest()` | `Request` | Parse raw Telegram JSON | Typed `TelegramUpdateDto` |
| `TGTextMessageDto::toArray()` | `TGTextMessageDto` | Filter nulls, prepare for API | Plain array for `sendQueryTelegram()` |
| `TelegramAnswerDto::fromData()` | Raw Telegram response array | Parse response | Typed `TelegramAnswerDto` |
| `ExternalMessageDto::fromRequest()` | `Request` | Parse external API request | Typed `ExternalMessageDto` |
| `VkUpdateDto` | VK webhook JSON | Parse VK payload | Typed `VkUpdateDto` |
| `VkAnswerDto` | VK API response | Parse response | Typed `VkAnswerDto` |

---

## 6. AI assistant data flow

```mermaid
sequenceDiagram
    participant S as TgMessageService
    participant AI as AiAssistantService
    participant P as AI Provider (GigaChat/etc)
    participant DB as Database
    participant J as SendAiTelegramMessageJob
    participant TG as Telegram

    S->>AI: processMessage(AiRequestDto)
    AI->>AI: Check AI_ENABLED and AiCondition.active
    AI->>P: HTTP request with message text
    P-->>AI: Generated draft text
    AI->>DB: INSERT ai_messages (text_ai=draft)
    AI->>J: SendAiTelegramMessageJob::dispatch(...)
    J->>TG: Send draft with Accept/Cancel inline keyboard
```

---

## 7. Webhook outbound flow (External Sources)

When an operator replies to an external user's topic:

```mermaid
sequenceDiagram
    participant OP as Operator
    participant TG as Telegram Group
    participant S as TgExternalMessageService
    participant J as SendWebhookMessage
    participant EXT as External Webhook URL
    participant DB as Database

    OP->>TG: Reply in forum topic (typeSource=supergroup)
    TG->>S: Route to TgExternalMessageService
    S->>DB: Find ExternalSource.webhook_url via BotUser
    S->>J: SendWebhookMessage::dispatch(url, payload)
    J->>EXT: POST webhook_url with message data
    EXT-->>J: HTTP response
    J->>DB: UPDATE external_messages.send_status
```

---

## Checklist

- [ ] All five major flows are documented
- [ ] Job retry/error paths are shown
- [ ] Data transformation points list all DTO factory methods
- [ ] AI flow covers the async job dispatch
