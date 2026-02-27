# Naming Conventions

> **Version:** 1.0.0
> **Context:** Read this file before creating any new file, class, method, variable, route, or database column.

---

## 1. Class naming

| Class type | Pattern | Examples |
|---|---|---|
| Controller | `{Resource}Controller` | `TelegramBotController`, `ExternalTrafficController` |
| Service | `{Domain}{Operation}Service` | `TgMessageService`, `ExternalTrafficService` |
| Abstract service | `Template{Concept}Service` | `TemplateMessageService`, `TemplateEditService` |
| Action | `{Verb}{Object}` | `SendContactMessage`, `DeleteForumTopic`, `BanMessage` |
| Job | `Send{Target}{Type}Job` or `{Verb}{Object}Job` | `SendTelegramMessageJob`, `TopicCreateJob` |
| DTO | `{Context}Dto` | `TelegramUpdateDto`, `TGTextMessageDto`, `ExternalMessageDto` |
| Enum | `{Concept}` (PascalCase noun) | `ButtonType`, `TelegramError`, `VkError` |
| Interface | `{Concept}Interface` | `AiProviderInterface` |
| Middleware | `{AuthMethod}Query` | `TelegramQuery`, `VkQuery`, `ApiQuery` |
| Model | Singular PascalCase | `BotUser`, `ExternalSource`, `AiCondition` |
| Test | `{ProductionClass}Test` | `SendContactMessageTest`, `TgMessageServiceTest` |

```php
// ✅ Correct
class SendContactMessage { ... }   // Action
class TgMessageService { ... }     // Service
class SendTelegramMessageJob { ... }  // Job

// ❌ Incorrect
class contactMessageSender { ... }  // wrong case
class TelegramSendJob { ... }       // wrong order (target before type)
class MessageService { ... }        // too generic — which platform?
```

---

## 2. Method naming

| Operation | Pattern | Examples |
|---|---|---|
| Primary action method | `execute()` | `SendContactMessage::execute()` |
| Service entry point | `handle()` | `TgMessageService::handle()` |
| Job entry point | `handle()` | `SendTelegramMessageJob::handle()` |
| DTO factory from request | `fromRequest(Request)` | `TelegramUpdateDto::fromRequest()` |
| Model factory/lookup | `getOrCreate{Context}(...)` | `getOrCreateByTelegramUpdate()` |
| Model lookup | `getBy{Field}(...)` | `getByTopicId()`, `getUserByChatId()` |
| Check / predicate | `is{Condition}()` | `isBanned()`, `isAvailable()` |
| Getters in DTO context | `get{Thing}()` | `getProviderName()`, `getModelName()` |

---

## 3. Variable and property naming

```php
// ✅ Correct — camelCase, descriptive
$botUser = BotUser::find($id);
$messageParamsDTO = new TGTextMessageDto(...);
$externalSource = ExternalSource::where('name', $name)->first();

// ❌ Incorrect
$u = BotUser::find($id);         // too short
$bot_user = BotUser::find($id);  // snake_case for PHP variable
$msg_dto = new TGTextMessageDto(...); // abbreviation + underscore
```

---

## 4. Route naming

| Route type | Pattern | Example |
|---|---|---|
| Webhook routes | No named route | `/api/telegram/bot` |
| External API | No named route (resource-style) | `/api/external/{id}/messages/` |
| Web routes | `{section}.{action}` | `docs.swagger-v1-json` |
| Static pages | No named route | `/`, `/live_chat_promo` |

---

## 5. Platform string conventions

Platform identifiers must be consistent across all models, DTOs, and services:

| Platform | String value |
|---|---|
| Telegram | `'telegram'` |
| VK | `'vk'` |
| External | `'external'` |

```php
// ✅ Correct
$botUser->platform = 'telegram';

// ❌ Incorrect — inconsistent casing or naming
$botUser->platform = 'Telegram';
$botUser->platform = 'tg';
$botUser->platform = 'TELEGRAM';
```

---

## 6. File and directory naming

| Type | Convention | Example |
|---|---|---|
| PHP classes | `PascalCase.php` | `TgMessageService.php` |
| Migration files | `{date}_{description}.php` | `2025_03_23_create_bot_users_table.php` |
| Config files | `snake_case.php` | `telegram.php`, `ai.php` |
| Test files | `{ClassName}Test.php` | `SendContactMessageTest.php` |
| Mock files | `{ClassName}Mock.php` | `TelegramUpdateDtoMock.php` |

---

## 7. Commit message format

```
issues-{number} | {type} {brief description}
```

| Type | Usage |
|---|---|
| `add` | New feature or file |
| `fix` | Bug fix |
| `update` | Enhancement to existing feature |
| `refactor` | Refactoring without behavior change |
| `remove` | Removal |
| `docs` | Documentation |
| `test` | Tests only |
| `style` | Formatting only |
| `chore` | Routine tasks |

```
✅ Correct: issues-85 | add external source token validation
✅ Correct: issues-37 | update swagger annotations for external API
❌ Incorrect: fixed bug
❌ Incorrect: issues-85 | Added validation for token
```

---

## 8. Custom naming deviations from Laravel defaults

| Deviation | Reason |
|---|---|
| `AiCondition` (not `UserAiSetting`) | Matches the database table `ai_conditions` and the domain concept |
| `TGTextMessageDto` (uppercase TG) | Legacy naming — Telegram abbreviation; do not rename existing class |
| `bot_query()` method on controllers | Matches the Telegram/VK terminology for webhook callback |
| `getOrCreate*` static methods on Models | Project chose model-level factory methods instead of a Repository pattern |

---

## Checklist

- [ ] New class names follow the patterns in section 1
- [ ] Method names follow the patterns in section 2
- [ ] Platform strings use the exact values from section 5
- [ ] Commit message follows the format in section 7
