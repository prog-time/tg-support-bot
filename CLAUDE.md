# CLAUDE.md

Instructions for Claude Code when working with the TG Support Bot project.

> **IMPORTANT:** This project has a structured `rules/` documentation system. Before starting any non-trivial task, read `rules/README.md` and the relevant domain/process files listed below.

---

## Rules Directory

The `rules/` directory is the source of truth for all architectural decisions, business rules, and coding standards.

**Always read before working:**

| Task Type | Files to Read |
|---|---|
| Any task | `rules/README.md` |
| Messaging / sending | `rules/domain/messaging.md` |
| User management / banning | `rules/domain/bot-users.md` |
| AI assistant logic | `rules/domain/ai-assistant.md` |
| External API integration | `rules/domain/external-sources.md` |
| Admin panel / manager UI | `rules/domain/admin-panel.md` |
| Database / migrations | `rules/database/schema.md` |
| HTTP routes / endpoints | `rules/api/endpoints.md` |
| Architecture decisions | `rules/process/architecture-design.md` |
| Logging / monitoring | `rules/process/observability.md` |
| Security / auth | `rules/process/security.md` |
| Tests | `rules/process/testing-strategy.md` |
| CI/CD / git hooks | `rules/process/ci-cd.md` |

---

## Project Description

TG Support Bot is a Laravel 12 application for customer support via Telegram and VK. The support team works in a Telegram supergroup with forum topics — each user gets their own topic thread. The project also integrates with external third-party systems via REST API.

**Supported platforms:**
- **Telegram** — main support channel (forum topics in supergroup)
- **VK** — secondary support channel
- **External Sources** — third-party integrations via REST API + webhooks

**Key integrations:**
- AI providers: OpenAI, DeepSeek, GigaChat (draft responses for manager review)
- Monitoring: Grafana + Loki + Sentry
- Live chat: Node.js server (port 3001)

---

## Tech Stack

| Component | Technology |
|---|---|
| Language | PHP 8.2+ |
| Framework | Laravel 12 |
| Database | PostgreSQL |
| Cache / Queue | Redis + Laravel Queue |
| Containers | Docker |
| API Documentation | L5-Swagger (annotations-based) |
| Static Analysis | PHPStan level 6 (larastan) |
| Code Formatting | Laravel Pint (PSR-12 + Laravel) |
| Testing | PHPUnit 11 + Mockery |
| Admin Panel | Filament 3 |
| Error Tracking | Sentry |
| Log Aggregation | Loki + Grafana + Promtail |
| Telegram Logging | prog-time/tg-logger |

---

## Architecture

```
HTTP Layer          app/Http/Controllers/ + app/Modules/*/Controllers/
     ↓
DTO Layer           app/DTOs/ + app/Modules/*/DTOs/
     ↓
Business Logic      app/Services/ + app/Modules/*/Services/ + app/Actions/
     ↓              ↓
Integration         ManagerInterfaceContract
app/Modules/Telegram/Api/   /          \
app/Modules/Vk/Api/   TelegramGroupInterface   AdminPanelInterface
     ↓              (forum topics)         (Filament web panel)
Queue Layer         app/Modules/*/Jobs/
     ↓
Data Layer          app/Models/ + PostgreSQL
```

### Layer Responsibilities

| Layer | Directory | Rule |
|---|---|---|
| Controllers | `app/Http/Controllers/`, `app/Modules/*/Controllers/` | Thin — receive request, dispatch job or call service, return response |
| Middleware | `app/Modules/*/Middleware/` | Validate incoming webhooks (Telegram, VK, External API auth) |
| DTOs | `app/DTOs/`, `app/Modules/*/DTOs/` | Parse and type incoming data via static `fromRequest()` |
| Services | `app/Services/`, `app/Modules/*/Services/` | Reusable business logic |
| Actions | `app/Actions/`, `app/Modules/*/Actions/` | Single isolated operations (one action = one thing) |
| Telegram/VK API | `app/Modules/Telegram/Api/`, `app/Modules/Vk/Api/` | Direct API calls only |
| Admin | `app/Modules/Admin/` | Filament resources, Livewire pages, SendReplyAction |
| Jobs | `app/Modules/*/Jobs/` | All async operations — message sending, webhooks |
| Models | `app/Models/` | Data operations only, no business logic, no API calls |

### Key Patterns

- **Action Pattern** — `app/Modules/*/Actions/` — static `execute()`, one responsibility
- **Service Pattern** — `app/Services/`, `app/Modules/*/Services/` — injected, reusable logic
- **DTO Pattern** — `app/DTOs/`, `app/Modules/*/DTOs/` — typed data transfer between layers
- **Queue Pattern** — all Telegram/VK API sends go through Jobs, never synchronously
- **Middleware Pattern** — webhook validation before controller runs
- **Contract Pattern** — `ManagerInterfaceContract` decouples manager UI from business logic

---

## Project Structure

```
app/
├── Actions/          # Shared isolated operations (Ai/)
├── Contracts/        # Interfaces (AiProviderInterface, ManagerInterfaceContract)
├── DTOs/             # Shared Data Transfer Objects (Ai/, Button/, Redis/)
├── Enums/            # Enumerations (ButtonType, TelegramError, VkError)
├── Helpers/          # Utilities (TelegramHelper, AiHelper, DateHelper)
├── Http/
│   └── Controllers/  # SimplePage, FilesController, SwaggerController, PreviewController
├── Logging/          # LokiHandler
├── Models/           # BotUser, Message, ExternalMessage, ExternalSource, AiMessage, etc.
├── Modules/
│   ├── Admin/        # Filament 3 admin panel
│   │   ├── Actions/  # SendReplyAction
│   │   ├── Filament/
│   │   │   ├── Pages/       # ConversationPage (Livewire)
│   │   │   └── Resources/   # ConversationResource, BotUserResource, ExternalSourceResource
│   │   └── Services/ # AdminPanelInterface (ManagerInterfaceContract implementation)
│   ├── External/     # External Sources integration
│   │   ├── Actions/, Controllers/, DTOs/, Jobs/, Middleware/, Services/
│   ├── Telegram/     # Telegram bot
│   │   ├── Actions/, Api/, Controllers/, DTOs/, Jobs/, Middleware/, Services/
│   │   └── Services/TelegramGroupInterface.php  # ManagerInterfaceContract implementation
│   └── Vk/           # VK bot
│       ├── Actions/, Api/, Controllers/, DTOs/, Jobs/, Middleware/, Services/
├── Providers/        # AppServiceProvider (binds ManagerInterfaceContract)
└── Services/         # Shared services (Ai/, Button/, File/, Swagger/, Webhook/)
```

---

## Development Commands

### Start the project
```bash
docker compose up -d
docker exec -it pet composer install
```

### Code formatting (run before every commit)
```bash
docker exec -it pet ./vendor/bin/pint
```

### Static analysis (run before every push)
```bash
docker exec -it pet ./vendor/bin/phpstan analyse
```

### Run tests
```bash
docker exec -it pet php artisan test
# or
docker exec -it pet ./vendor/bin/phpunit
```

### Run specific test
```bash
docker exec -it pet php artisan test --filter=TestName
```

---

## Code Standards

### Formatting (PSR-12 + Laravel, enforced by Pint)

- Indentation: 4 spaces
- Single quotes for strings
- Short array syntax `[]`
- Trailing comma in multiline arrays
- Remove unused imports
- Sort imports alphabetically

### Naming Conventions

| Element | Convention | Example |
|---|---|---|
| Classes | `PascalCase` | `SendBannedMessage` |
| Methods, variables | `camelCase` | `getByTopicId()` |
| Constants | `UPPER_SNAKE_CASE` | `MAX_RETRIES` |
| Migration files | `snake_case` | `create_bot_users_table` |
| Actions | Static `execute()` | `GetChat::execute($chatId)` |
| DTOs | Static `fromRequest()` | `TelegramUpdateDto::fromRequest($request)` |
| Jobs | `*Job` suffix | `SendTelegramMessageJob` |

### PHPDoc (required for all public methods)

```php
/**
 * Brief method description.
 *
 * @param BotUser $botUser The target bot user
 * @return TelegramAnswerDto
 * @throws TelegramException When Telegram API call fails
 */
public static function execute(BotUser $botUser): TelegramAnswerDto
{
}
```

---

## Business Rules Summary

### Messaging

- All message sending to Telegram/VK must go through **queue Jobs**, never synchronously
- Every sent/received message must be saved to the `messages` table
- Banned users receive a banned notification, not a regular reply
- Each bot user has exactly one Telegram forum topic thread

### Bot Users

- Every interaction creates or finds a `BotUser` record
- Users are identified by `chat_id` + `platform` (not `chat_id` alone)
- Banning sets `is_banned = true`, `banned_at`, and closes the Telegram topic

### AI Assistant

- AI is disabled by default (`AI_ENABLED=false`)
- AI runs through a **separate Telegram bot** (`TELEGRAM_AI_BOT_TOKEN`) that is added to the same supergroup
- The AI bot webhook URL is `POST /api/ai-bot/webhook`, protected by `AiBotQuery` middleware (`TELEGRAM_AI_BOT_SECRET`)
- `AI_AUTO_REPLY=false` (default): AI posts a draft with "Accept / Cancel" inline buttons; manager reviews before sending
- `AI_AUTO_REPLY=true`: AI posts the reply directly to the topic; it is immediately sent to the user via `SendReplyAction`
- The AI bot only replies to messages whose `from.id` equals `TELEGRAM_BOT_ID` (forwarded user messages from the main bot)
- The AI bot does NOT reply when `MANAGER_INTERFACE=admin_panel`
- Supported providers: OpenAI, DeepSeek, GigaChat (set via `AI_DEFAULT_PROVIDER`)
- Register the AI bot webhook with: `docker exec -it pet php artisan ai-bot:set-webhook`
- AI conversation history is sourced from the `messages` table (no Redis cache), bounded by `AI_MAX_CONTEXT_TOKENS` (default 3000) using a `mb_strlen / 4` token heuristic with sliding-window trimming
- AI system prompt lives in `resources/ai/system-prompt.blade.php` (Blade template — variables only, no `@if`/`@foreach`/`@include`/`@php`); path is configured by `config/ai.php @ system_prompt_path`
- AI drafts NEVER write to `messages` (only to `ai_messages`); a `messages` row appears only when the message is actually sent to the user — this invariant is what makes "any outgoing row = delivered" safe for the chat-history assembler

### External Sources

- Requests must be authenticated with a bearer token from `external_source_access_tokens`
- When the team replies to an external user, a webhook is sent to `external_sources.webhook_url`

### Manager Interface

- `MANAGER_INTERFACE=telegram_group` (default) — managers work via Telegram supergroup with forum topics
- `MANAGER_INTERFACE=admin_panel` — managers work via the `/admin` web panel (Filament 3)
- Switching: change `.env` + restart the `php-fpm` container (`docker compose restart app`)
- Does not require `php artisan migrate` or any DB changes
- See `rules/domain/admin-panel.md` for full rules and `docs/switching-manager-interface.md` for runbook

---

## Security Rules

- All main bot Telegram webhooks validated by `TelegramQuery` middleware (`X-Telegram-Bot-Api-Secret-Token` vs `TELEGRAM_SECRET_KEY`)
- AI bot webhook validated by `AiBotQuery` middleware (`X-Telegram-Bot-Api-Secret-Token` vs `TELEGRAM_AI_BOT_SECRET`)
- All VK webhooks validated by `VkQuery` middleware
- All External API requests validated by `ApiQuery` middleware (bearer token)
- Never pass raw `Request` objects to Services or Actions — use DTOs
- Never use raw SQL string concatenation — use Eloquent / query builder
- Never commit `.env` files or hardcode secrets
- Never log tokens, passwords, or API keys

---

## Commit Rules

### Message Format

```
issues-{number} | {brief description}
```

### Examples

```
issues-123 | add VK sticker support
issues-45 | fix telegram webhook error handling
issues-78 | update rules documentation
```

### Change Types

- `add` — new feature
- `fix` — bug fix
- `update` — update existing functionality
- `refactor` — refactoring (no behavior change)
- `remove` — deletion
- `docs` — documentation only
- `test` — tests only
- `style` — formatting only
- `chore` — routine maintenance

---

## Branch Naming

```
issues-{number}
issues-{number}-{brief-description}
```

Examples: `issues-38`, `issues-45-fix-telegram-webhook`

---

## Testing

### Test Location Rule

Test file location mirrors source file location:

| Source | Test |
|---|---|
| `app/Actions/Telegram/GetChat.php` | `tests/Unit/Actions/Telegram/GetChatTest.php` |
| `app/Services/Tg/TgMessageService.php` | `tests/Unit/Services/Tg/TgMessageServiceTest.php` |

### Requirements

- New functionality must be covered by tests before merge
- Bug fixes must include a regression test
- Unit tests use `Http::fake()` — no real API calls
- Feature tests use `RefreshDatabase` trait
- Test naming: `test_can_do_something` or `test_throws_exception_when_invalid`
- Tests run with SQLite in-memory database (see `phpunit.xml`)

### Test Structure

```
tests/
├── Unit/       # Actions, Services, Helpers, Models
├── Feature/    # HTTP endpoints, integrations
├── Mocks/      # Mock objects
├── Stubs/      # Raw data stubs (webhook payloads)
└── Traits/     # Reusable traits
```

---

## Git Hooks

| Hook | Script | What it checks |
|---|---|---|
| `pre-commit` | `linting/pre-commit-check.sh` | Laravel Pint formatting |
| `pre-push` | `linting/pre-push-check.sh` | PHPStan level 6 + PHPUnit |

Never bypass hooks with `--no-verify`.

---

## Post-Task Verification

Before marking any task complete:

1. All new public methods have PHPDoc with type hints
2. New classes have corresponding test files in `tests/`
3. Laravel Pint passes with no changes needed
4. PHPStan level 6 passes with 0 errors
5. All tests pass
6. If schema changed → `rules/database/schema.md` updated
7. If routes changed → `rules/api/endpoints.md` updated
8. If business rules changed → relevant `rules/domain/*.md` updated
9. No secrets committed (`.env` excluded from git)
