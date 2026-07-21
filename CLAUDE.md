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
- **Max** — additional support channel
- **Avito** — built-in support channel (`app/Modules/Avito/`), same tier as Telegram/VK/Max; free, ships with core, text-only in v1 (see `rules/domain/messaging.md`)
- **Email** — built-in support channel (`app/Modules/Email/`), same tier as Telegram/VK/Max/Avito; free, ships with core, text-only in v1. No HTTP webhook — incoming mail is polled via IMAP (`php artisan email:poll`, scheduled every minute), outgoing replies go over SMTP (`SendEmailMessageJob`). Works with any provider (Yandex, Mail.ru, Gmail with an app-password, a corporate mailbox); no per-provider presets or OAuth in v1 (see `rules/domain/messaging.md`)
- **External Sources** — third-party integrations via REST API + webhooks
- **Pluggable platform modules** — additional channels can still be shipped as separate (incl. paid, private) Composer packages that implement `App\Contracts\PlatformChannel` and self-register in `App\Platform\PlatformChannelRegistry` — no core edits required. Avito is NOT an example of this anymore (it is a built-in module); the registry remains for future third-party platforms

**Key integrations:**
- AI providers: OpenAI, DeepSeek, GigaChat (draft responses for manager review)
- Monitoring: Laravel Telescope (requests/logs/queries/jobs/exceptions debug dashboard)
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
| Admin Panel | Livewire 3 + Blade (custom screens; no Filament) |
| Logs | Rotating files (`storage/logs/`), viewed via `php artisan pail` or Telescope |
| Debug / Monitoring | Laravel Telescope (`/telescope`) |
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
     ↓              (forum topics)         (Livewire web panel)
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
| Admin | `app/Modules/Admin/` | Custom full-page Livewire screens (login + chat workspace + Settings), standard Laravel auth, SendReplyAction, admin design system |
| Jobs | `app/Modules/*/Jobs/` | All async operations — message sending, webhooks |
| Models | `app/Models/` | Data operations only, no business logic, no API calls |

### Key Patterns

- **Action Pattern** — `app/Modules/*/Actions/` — static `execute()`, one responsibility
- **Service Pattern** — `app/Services/`, `app/Modules/*/Services/` — injected, reusable logic
- **DTO Pattern** — `app/DTOs/`, `app/Modules/*/DTOs/` — typed data transfer between layers
- **Queue Pattern** — all Telegram/VK API sends go through Jobs, never synchronously
- **Middleware Pattern** — webhook validation before controller runs
- **Contract Pattern** — `ManagerInterfaceContract` decouples manager UI from business logic
- **Platform Registry Pattern** — `PlatformChannel` + `PlatformChannelRegistry` (`app/Platform/`) let external (incl. paid, private) platform packages self-register delivery for a `platform` key without editing the core. Built-in channels (Telegram/VK/Max/Avito) do NOT go through this registry — they are wired directly into `DeliverAiAnswerToUser`, `SendReplyAction`, and `SendFeedbackForm` via explicit `case` branches on `platform`. The registry exists for future third-party platforms only
- **Settings Pattern** — `SettingsService` + `SettingKeyRegistry` (`app/Services/Settings/`) provide a unified `get/set/has/forget` API for runtime-editable settings (DB → optional `config()` fallback, Redis cache, `Crypt` encryption for secrets, type coercion); all channel/AI keys have `config => null` (DB-only, no .env fallback); known keys registered in `SettingKeyRegistry`
- **Admin Design System Pattern** — Tailwind v4 tokens in `resources/css/app.css @theme` + shared Blade components in `resources/views/components/admin/`. All admin screens — login, chat workspace and Settings — are custom Livewire/Blade on this design system. There is no Filament; authentication uses the standard Laravel `web` guard (login: `App\Livewire\Auth\LoginPage`).

---

## Project Structure

```
app/
├── Actions/          # Shared isolated operations (Ai/)
├── Contracts/        # Interfaces (AiProviderInterface, ManagerInterfaceContract, PlatformChannel)
├── Livewire/
│   ├── Auth/         # Standalone full-page login screen (standard Laravel auth, no Filament)
│   │   └── LoginPage.php                # GET /admin/login — authenticate against the web guard
│   ├── Chat/         # Standalone full-page Livewire workspace (chrome-free)
│   │   └── ConversationPage.php         # GET /admin/chats — full-screen 3-column manager workspace
│   └── Settings/     # Custom full-page Livewire components for Settings section
│       ├── GeneralSettingsPage.php       # /admin/settings/general
│       ├── IntegrationsListPage.php      # /admin/settings/integrations
│       ├── IntegrationChannelPage.php   # /admin/settings/integrations/{channel}
│       ├── AvitoIntegrationPage.php     # /admin/settings/avito (built-in Avito module credentials)
│       ├── EmailIntegrationPage.php     # /admin/settings/email (built-in Email module IMAP/SMTP credentials)
│       ├── AiAssistantPage.php          # /admin/settings/ai
│       ├── AiProviderAccessPage.php     # /admin/settings/ai/{provider}
│       ├── ApiWebhooksPage.php          # /admin/settings/api-webhooks (admin-only, source card list)
│       ├── ApiWebhookSourcePage.php     # /admin/settings/api-webhooks/{source} (per-source edit)
│       └── TeamPage.php                 # /admin/settings/team (admin-only, operator management)
├── Platform/         # PlatformChannelRegistry — registry for pluggable platform modules
├── DTOs/             # Shared Data Transfer Objects (Ai/, Button/, Redis/)
├── Services/
│   └── Settings/     # Settings persistence layer: SettingsService, SettingKeyRegistry
├── Enums/            # Enumerations (ButtonType, TelegramError, VkError)
├── Helpers/          # Utilities (TelegramHelper, AiHelper, DateHelper)
├── Http/
│   └── Controllers/  # SimplePage, FilesController, SwaggerController, PreviewController
├── Models/           # BotUser, Message, ExternalMessage, ExternalSource, AiMessage, etc.
├── Modules/
│   ├── Admin/        # Admin panel — custom Livewire screens, standard Laravel auth (no Filament)
│   │   ├── Actions/  # SendReplyAction, InviteOperator
│   │   ├── AdminServiceProvider.php  # All admin routes: login/logout, /admin→/admin/chats, /admin/chats, /admin/settings/*
│   │   └── Services/ # AdminPanelInterface + ChannelStatusService + WebhookRegistrationService
│   ├── Avito/        # Avito bot — built-in channel (text-only v1), same tier as Telegram/VK/Max
│   │   ├── Actions/  # SendMessageAvito, SendBannedMessageAvito, SendStartMessageAvito
│   │   ├── Api/AvitoMethods.php       # Direct Avito Messenger API calls
│   │   ├── Console/SetWebhookCommand.php  # php artisan avito:set-webhook
│   │   ├── Controllers/AvitoBotController.php
│   │   ├── DTOs/     # AvitoUpdateDto, AvitoTextMessageDto, AvitoAnswerDto
│   │   ├── Jobs/SendAvitoSimpleMessageJob.php
│   │   ├── Middleware/AvitoQuery.php  # validates the {secret} URL segment
│   │   ├── Services/AvitoMessageService.php
│   │   ├── AvitoServiceProvider.php   # registers routes.php + SetWebhookCommand
│   │   └── routes.php                 # POST /api/avito/bot/{secret?}
│   ├── External/     # External Sources integration
│   │   ├── Actions/, Controllers/, DTOs/, Jobs/, Middleware/, Services/
│   ├── Telegram/     # Telegram bot
│   │   ├── Actions/, Api/, Controllers/, DTOs/, Jobs/, Middleware/, Services/
│   │   ├── Jobs/SendAvitoTelegramMessageJob.php  # forwards Avito messages into the supergroup topic when telegram.group_id is set
│   │   └── Services/TelegramGroupInterface.php  # ManagerInterfaceContract implementation
│   └── Vk/           # VK bot
│       ├── Actions/, Api/, Controllers/, DTOs/, Jobs/, Middleware/, Services/
├── Providers/        # AppServiceProvider (binds ManagerInterfaceContract)
└── Services/         # Shared services (Ai/, Button/, File/, Swagger/, Webhook/)

resources/
├── css/app.css        # Tailwind v4 @theme tokens: admin design system (Inter, accent, sidebar, input, …)
├── views/
│   ├── components/
│   │   └── admin/    # Shared admin Blade components (Stage 1)
│   │       ├── sidebar.blade.php, nav-item.blade.php, card.blade.php
│   │       ├── form-field.blade.php, button-primary.blade.php
│   │       ├── button-secondary.blade.php, toggle.blade.php
│   ├── layouts/
│   │   ├── admin-settings.blade.php  # Dark-sidebar layout for custom settings pages
│   │   ├── admin-chat.blade.php      # Minimal full-screen layout for the chat workspace + Alpine toast renderer
│   │   └── admin-auth.blade.php      # Minimal layout for the standalone login screen
│   └── livewire/
│       ├── chat/
│       │   └── conversation-page.blade.php            # View for App\Livewire\Chat\ConversationPage
│       └── settings/
│           ├── general-settings-page.blade.php        # View for GeneralSettingsPage
│           ├── integrations-list-page.blade.php       # View for IntegrationsListPage
│           ├── integration-channel-page.blade.php    # View for IntegrationChannelPage
│           ├── avito-integration-page.blade.php      # View for AvitoIntegrationPage
│           ├── email-integration-page.blade.php      # View for EmailIntegrationPage
│           ├── ai-assistant-page.blade.php           # View for AiAssistantPage
│           ├── ai-provider-access-page.blade.php    # View for AiProviderAccessPage
│           ├── api-webhooks-page.blade.php          # View for ApiWebhooksPage (source card list)
│           ├── api-webhook-source-page.blade.php   # View for ApiWebhookSourcePage (per-source edit)
│           └── team-page.blade.php                 # View for TeamPage (operator management)
```

---

## Development Commands

### Start the project
```bash
docker compose up -d
docker exec -it pet composer install
docker exec -it pet npm ci && docker exec -it pet npm run build
```

The bind mount `.:/var/www` shadows `vendor/`, `node_modules/` and
`public/build/` from the image — they must be installed into the mounted
directory. Containers run as `www-data` (uid 33); those directories plus
`storage/` and `bootstrap/cache/` must be owned by uid 33 on the host.
Full first-time setup: see `README.md` → «Установка через Docker Compose: с нуля».

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

- All message sending to Telegram/VK/Max/Avito must go through **queue Jobs**, never synchronously
- Every sent/received message must be saved to the `messages` table
- Banned users receive a banned notification, not a regular reply
- Each bot user has exactly one Telegram forum topic thread
- **Avito v1 is text-only.** Incoming attachments are not accepted; a file attached to a manager's reply is skipped with a warning log instead of being sent (see `SendReplyAction::sendAvitoReply()`). Avito Messenger has no inline-keyboard support, so the feedback rating form is a one-way text prompt — the user's star-tap callback is not received, `HandleFeedbackRating` is never invoked for Avito, and the `Feedback` record stays in `status='awaiting_rating'` forever. Rating-callback handling in `AvitoBotController` is marked `TODO` pending confirmation of Avito's callback mechanism

### Bot Users

- Every interaction creates or finds a `BotUser` record
- Users are identified by `chat_id` + `platform` (not `chat_id` alone)
- `bot_users.chat_id` is a `string` column (widened from `unsignedBigInteger`) so pluggable/built-in platforms with non-numeric ids (e.g. Avito's `u2i-...` format) can be stored natively; numeric platforms (telegram/vk/max) still store their id as text
- Banning sets `is_banned = true`, `banned_at`, and closes the Telegram topic

### AI Assistant

- AI is disabled by default (`ai.enabled = false` in settings DB); can be toggled from the admin panel at `/admin/settings/ai`. The master toggle **cannot be enabled** until the «Бот AI помощника» integration is configured (`telegram_ai.token` set — `ChannelStatusService::telegramAi()`): AI replies post to the supergroup as the AI bot, so without it they fail (Telegram 404). The page shows a notice + link to configure it; disabling is always allowed
- AI runs through a **separate Telegram bot** (token stored as `telegram_ai.token` in settings DB) that is added to the same supergroup
- The AI bot webhook URL is `POST /api/ai-bot/webhook`, protected by `AiBotQuery` middleware (secret stored as `telegram_ai.secret` in settings DB)
- `ai.auto_reply = false` (default): AI posts a draft with "Accept / Cancel" inline buttons; manager reviews before sending
- `ai.auto_reply = true`: AI posts the reply directly to the topic; it is immediately sent to the user; enabling via admin panel requires explicit confirmation
- The AI bot processes updates arriving at `POST /api/ai-bot/webhook`; `ShouldAiReply` gates replies on AI enabled, `MANAGER_INTERFACE=telegram_group`, private-chat source, message type, replyable text, and active user — no configured bot ID comparison is used
- The AI bot does NOT reply when `MANAGER_INTERFACE=admin_panel`
- Supported providers: OpenAI, DeepSeek, GigaChat (set via `ai.default_provider` in settings DB or from `/admin/settings/ai`)
- Register the AI bot webhook with: `docker exec -it pet php artisan ai-bot:set-webhook`
- AI conversation history is sourced from the `messages` table (no Redis cache), bounded by `ai.max_context_tokens` (default 3000, stored in settings DB) using a `mb_strlen / 4` token heuristic with sliding-window trimming
- AI system prompt: stored **only in the DB** under `ai.system_prompt` (via `SettingsService`), used **verbatim** — no templating / variable substitution, no file and no default (empty until an admin saves one; an empty value sends no system prompt). The «Системный промпт» field at `/admin/settings/ai` is loaded from and saved to that key (`SettingsService::set('ai.system_prompt', …)`) — no filesystem involved. `AiSystemPromptLoader::render()` reads the DB value and supplies it to every provider at request time
- AI provider credentials (API keys, base URLs, models, tokens, cert path) are managed at `/admin/settings/ai/{provider}` and stored encrypted in the `settings` table via `SettingsService`. The «Проверить и сохранить» button runs a **verify-before-save** flow (`AiProviderAccessPage::connect()` → `App\Modules\Ai\Services\AiProviderVerificationService`): credentials are checked against the provider API (OpenAI/DeepSeek minimal chat-completion; GigaChat OAuth token request) and nothing is persisted on failure — analogous to channel integrations' `WebhookRegistrationService::verifyX()`
- AI behaviour settings managed from `/admin/settings/ai`: auto-reply toggle and system prompt only. The `ai.rate_limit.*` and `ai.disable_timeout` settings have been removed entirely (no UI, no registry keys, no internal rate-limit logic). The `ai.max_context_tokens`, `ai.confidence_threshold`, `ai.auto_escalation` and `ai.enable_logging` keys are NOT exposed in the UI — `max_context_tokens`/`confidence_threshold` run on their registry defaults (3000 / 0.8); `auto_escalation`/`enable_logging` are vestigial (logging is always on)
- AI drafts NEVER write to `messages` (only to `ai_messages`); a `messages` row appears only when the message is actually sent to the user — this invariant is what makes "any outgoing row = delivered" safe for the chat-history assembler
- AI runs across all user platforms (`telegram`, `vk`, `max`, `avito`). Triggers: `TelegramBotController::maybeDispatchAi()` for TG, `VkMessageService::maybeDispatchAi()` for VK, `MaxMessageService::maybeDispatchAi()` for Max, `AvitoMessageService::maybeDispatchAi()` for Avito. Triggering is text-only — attachments do not start AI. Gating still goes through `ShouldAiReply` (TG-DTO and platform-agnostic variants share the same rules: AI_ENABLED, `MANAGER_INTERFACE=telegram_group`, replyable text, user active)
- Final delivery of an AI answer to the user (Accept and auto-reply) is routed by `BotUser.platform` through `App\Modules\Ai\Actions\DeliverAiAnswerToUser` → `SendTelegramMessageJob` / `SendVkMessageJob` / `SendMaxMessageJob` / `case 'avito'` (built-in, wired directly — not via the registry). Any other, non-built-in platform is delegated to a `PlatformChannel` registered in `App\Platform\PlatformChannelRegistry` by a pluggable third-party module. The Accept callback still edits the supergroup draft via `SendTelegramMessageJob` using the AI bot token regardless of user platform
- All channel credentials, AI provider credentials, and AI behaviour settings are stored exclusively in the `settings` DB table (no `.env`/`config()` fallback for these keys — `config => null` in registry). Values must be entered via the admin UI. There is **no env-to-DB seeding command** — configure everything from `/admin/settings/*`.

### Settings Persistence Layer

- Runtime-editable application settings are stored in the `settings` table and accessed exclusively via `SettingsService` (`app/Services/Settings/SettingsService.php`)
- **Reading priority:** DB row → `config()` default (declared in `SettingKeyRegistry`) → `null`. Channel credentials and AI settings have `config => null` (no fallback) — they return `null` until explicitly saved.
- **Never call `config()` directly** for a setting that may be overridden at runtime — use `app(\App\Services\Settings\SettingsService::class)->get('key')` instead
- Secret keys (`is_secret=true` in `SettingKeyRegistry`) are encrypted with `Crypt::encrypt()` before DB write and decrypted transparently in `get()` — never read the raw `settings.value` column for secret keys
- Cache: values cached forever in the default store (Redis); invalidated on `set()` / `forget()`
- Known keys and their types/fallbacks/secret flags are registered in `SettingKeyRegistry::$keys`
- In-scope keys with `config => null`: all `telegram.*`, `telegram_ai.*`, `vk.*`, `max.*`, `avito.*`, all `ai.*` credentials and behaviour settings. Infrastructure keys (`app.manager_interface`) retain their `config()` fallback.
- Avito settings keys: `avito.client_id`, `avito.client_secret`(secret), `avito.user_id` (auto-captured, non-secret), `avito.base_url` (non-secret), `avito.webhook_secret`(secret). There is no `config/avito.php` — credentials are read exclusively via `SettingsService`, no `.env` involved. Register the Avito webhook with `docker exec -it pet php artisan avito:set-webhook` (subscribes `POST /api/avito/bot/{secret}` — where `{secret}` is `avito.webhook_secret` — with the Avito Messenger API)
- The General Settings screen (`/admin/settings/general`, `app/Livewire/Settings/GeneralSettingsPage.php`) provides a custom Livewire/Blade UI for editing only `telegram.template_topic_name` (Telegram topic-name template). Bot name (`app.bot_name`), description (`app.bot_description`), and the manager-interface radio (`app.manager_interface`) were removed from this screen. Uses the admin design system (Tailwind v4 tokens + `<x-admin.*>` Blade components)

### Channel Integrations (Settings)

- The Integrations screen (`/admin/settings/integrations`, `app/Livewire/Settings/IntegrationsListPage.php`) shows Telegram, Telegram AI bot, VK, MAX, and Avito channel cards. Telegram/Telegram AI/VK/MAX status is computed by `ChannelStatusService`; the Avito card's `avitoConnected` flag is computed independently in `IntegrationsListPage::mount()` (`avito.client_id` + `avito.client_secret` both present)
- Per-channel config forms (`/admin/settings/integrations/{channel}`, `IntegrationChannelPage`) let admins configure tokens, keys, and identifiers for each platform. Route constraint: `channel` ∈ `telegram|telegram_ai|vk|max`
- Avito is configured on its own dedicated screen, `/admin/settings/avito` (`AvitoIntegrationPage`, route name `admin.settings.avito`) — NOT through `IntegrationChannelPage`. Fields: `avito.client_id`, `avito.client_secret`(secret), `avito.base_url` (defaults to `https://api.avito.ru`), `avito.webhook_secret`(secret, optional — blank leaves the webhook endpoint open with a warning log). The «Сохранить» button runs a verify-before-save flow (`AvitoVerificationService::verify()`: OAuth `client_credentials` token request + `core/v1/accounts/self`); on success the Avito account id is auto-captured into `avito.user_id` (never entered manually, mirroring `telegram_ai.id`). Admin-only; route is always registered since Avito ships with core (no `class_exists()` gate)
- Email is configured on its own dedicated screen, `/admin/settings/email` (`EmailIntegrationPage`, route name `admin.settings.email`) — NOT through `IntegrationChannelPage` (its generic IMAP/SMTP host/port/encryption fields don't fit the `telegram|telegram_ai|vk|max` shape). Fields: `email.imap_host`, `email.imap_port` (default 993), `email.imap_encryption` (default `ssl`), `email.smtp_host`, `email.smtp_port` (default 587), `email.smtp_encryption` (default `tls`), `email.username`, `email.password`(secret), `email.from_address`, `email.from_name`, `email.poll_interval` (informational in v1 — the `email:poll` scheduler entry runs a fixed `everyMinute()`, not read from this key). The «Сохранить» button runs a verify-before-save flow (`EmailVerificationService::verify()`: a real IMAP login via webklex/php-imap AND a real SMTP handshake via a Symfony Mailer transport's `start()`/`stop()` — both must succeed); nothing is persisted if either check fails. Admin-only; route is always registered since Email ships with core
- Telegram channel page (`channel=telegram`) covers: `telegram.group_id`, `telegram.token`(secret), `telegram.secret_key`(secret) — **all three required** (`IntegrationChannelPage::validateFields()` blocks «Сохранить»/`connect()` with per-field errors until each is filled; fields are pre-filled from settings, so an existing config already satisfies this). The `telegram.template_topic_name` field moved to the General settings screen; the `telegram.bot_id` setting was removed (unused at runtime)
- Telegram AI bot page (`channel=telegram_ai`) has two inputs: `telegram_ai.token`(secret) and `telegram_ai.secret`(secret) — **both required** (`IntegrationChannelPage::validateFields()` blocks «Сохранить»/`connect()` with per-field errors until filled; fields are pre-filled from settings, so an existing config already satisfies this). The bot's **`telegram_ai.id` and `telegram_ai.username` are captured automatically** from `getMe` during the verify-before-save step and persisted — there is no manual username field. (`telegram_ai.id`/`telegram_ai.username` are informational — not compared at runtime.) Webhook registration uses `php artisan ai-bot:set-webhook` (shown in the instruction panel)
- Channel config is read/written exclusively via `SettingsService` using the registry keys `telegram.*`, `telegram_ai.*`, `vk.*`, `max.*`
- All secret fields (tokens, keys) are rendered as `type="password"` inputs; blank submission does not overwrite an existing stored secret
- The primary action button is **«Сохранить»** and follows a **verify-before-save** flow: (1) field validation, (2) resolve verification token (entered value or stored fallback), (3) call `WebhookRegistrationService::verifyX($token)` — if it fails, show error and do NOT persist anything; (4) on success, persist via `SettingsService`, then register webhook (telegram|vk|max) or show success notice (telegram_ai)
- `WebhookRegistrationService` provides `verifyTelegram/verifyVk/verifyMax($token)` (pre-save platform check, explicit token) and `registerTelegram/registerVk/registerMax()` (webhook registration, reads from `SettingsService`)
- Verify methods: Telegram uses `getMe`, VK uses `groups.getById`, MAX uses `GET /me` — all with a 10 s timeout, catch exceptions → `success=false`
- Tokens are never logged — only non-sensitive context (registered URL, HTTP status code)
- See `rules/domain/admin-panel.md` (BR-013 through BR-016) and `rules/process/security.md` (Secrets in the DB settings table)

### Team Screen (`/admin/settings/team`)

- The Team screen (`app/Livewire/Settings/TeamPage.php`) is admin-only — non-admins are redirected to `admin.settings.general` in `mount()` (see `rules/domain/admin-panel.md` BR-026)
- Inviting a new operator calls `App\Modules\Admin\Actions\InviteOperator::execute(email, role)`: creates the `User` immediately with a 16-char secure password (`Str::password(16)`) and returns `['user', 'password']` — **no email is sent** (see BR-027)
- `TeamPage::invite()` reveals the generated password to the admin once (copy/dismiss) so it can be handed to the operator; the plain-text password is NEVER logged
- Delete requires a two-step confirmation (`confirmDelete(userId)` → `deleteMember()`). Admins cannot delete themselves — `deleteMember()` guards against `Auth::id() === $confirmDeleteId` (see BR-028)
- Online status: v1 stub — renders a «—» placeholder badge. No `last_seen_at` column or tracking middleware. Real online-tracking is a separate future task
- Avatar initials: deterministic, derived from user name (two-word: first letters; single-word: first 2 chars) or email local-part fallback; color: `crc32(email) % 8` from 8-color palette

### External Sources

- Requests must be authenticated with a bearer token from `external_source_access_tokens`
- When the team replies to an external user, a webhook is sent to `external_sources.webhook_url`
- Bearer tokens are managed in the admin panel at `/admin/settings/api-webhooks` (`ApiWebhooksPage`) — admin-only
- Token length: 64 characters (`Str::random(64)` in `ExternalSourceTokensService::generateToken()`)
- Token active flag: `external_source_access_tokens.active` — inactive tokens (`active=false`) are rejected by `ApiQuery` middleware with 401
- Token values are never logged or displayed in full — only a one-time reveal after regeneration, followed by dismissal
- IP allowlist: `external_sources.allowed_ips` (JSON, editable on the per-source page `/admin/settings/api-webhooks/{source}`) restricts which IPs may call the API. Non-empty → `ApiQuery` rejects (403) requests whose IP isn't listed; empty/NULL → no restriction (`ExternalSource::isIpAllowed()`). The per-source edit page no longer has the secret-key/events design placeholders.
- See `rules/domain/admin-panel.md` (BR-023, BR-024, BR-025) and `rules/domain/external-sources.md`

### Feedback Form

- When `CloseTopic::execute()` closes a conversation, `SendFeedbackForm` creates a `Feedback` record (`status='awaiting_rating'`) and sends a 5-star inline-keyboard rating form to the user on their platform (Telegram/VK/Max/Avito are built-in `case` branches; any other, non-built-in platform is delegated to a `PlatformChannel` registered in `App\Platform\PlatformChannelRegistry`)
- Telegram callback_data format: `feedback_rate_{botUserId}_{feedbackId}_{score}` (score 1..5) — handled in `TelegramBotController::checkBotQuery()`
- VK rating callbacks arrive as `type=message_event` with `payload.command` containing `feedback_rate_*` — handled in `VkBotController`
- Max rating callbacks arrive as `update_type=message_callback` with `callback.payload` containing `feedback_rate_*` — handled in `MaxBotController`
- **Avito v1 has no inline-keyboard support**, so `SendFeedbackForm`'s `case 'avito'` branch sends the rating request as plain text only — there is no tappable star widget. Consequently no rating callback ever arrives: `HandleFeedbackRating` is never invoked for Avito, and the `Feedback` record stays in `status='awaiting_rating'` indefinitely. Rating-callback handling is left as a `TODO` in `AvitoBotController` pending confirmation of Avito's callback mechanism
- On rating click (Telegram/VK/Max): `HandleFeedbackRating` saves `rating`, sets `status='completed_no_comment'`, edits message to thank-you text. `comment` stays NULL — no comment capture is implemented
- Every close event creates a new `Feedback` record — history accumulates
- `Feedback` records persist in the DB; the legacy admin UI for them was removed (a redesigned screen is pending)

### Manager Interface

- `MANAGER_INTERFACE=telegram_group` (default) — managers work via Telegram supergroup with forum topics
- `MANAGER_INTERFACE=admin_panel` — managers work via the `/admin/chats` workspace (full-screen, chrome-free, `App\Livewire\Chat\ConversationPage`)
- Switching via `.env` only: change `MANAGER_INTERFACE` + `docker compose restart app`. **This is no longer editable from the admin panel** — the General Settings screen's manager-interface radio was removed.
- The `ManagerInterfaceContract` DI binding in `AppServiceProvider::register()` reads from `config('app.manager_interface')` at boot, NOT from `SettingsService` — this is intentional to avoid DB dependency at container startup
- Does not require `php artisan migrate` or any DB changes (for mode switching itself)
- See `rules/domain/admin-panel.md` for full rules (BR-001 through BR-025)

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
| `pre-push` | `linting/pre-push-check.sh` | PHPStan level 6 + PHPUnit |

The `pre-commit` and `prepare-commit-msg` hooks have been removed. Pint formatting is not enforced by a local hook or CI at this time — run it manually before committing (`vendor/bin/pint`). PHPStan and tests are enforced by CI and by the `pre-push` hook.

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
