# API Contract Rules

> **Context:** Read this file before adding, modifying, or deleting any HTTP endpoint.
> **Version:** 1.0

---

## 1. Core Principle

The API is defined only by Swagger (OpenAPI).

- Swagger is the contract
- Swagger is the documentation
- Swagger is the integration surface
- Swagger is the source of truth
- No parallel Markdown descriptions of endpoints are allowed

If behavior exists in code but not in Swagger, it is a bug.
If Swagger exists but code differs, the implementation is a bug.

---

## 2. Swagger Location

The project uses **L5-Swagger** (darkaonline/l5-swagger) to generate OpenAPI documentation from PHP annotations.

| Resource | Location |
|---|---|
| Swagger UI | `GET /docs/swagger-v1-ui` |
| Swagger JSON | `GET /docs/swagger-v1-json` |
| Annotations | PHP docblocks in Controllers and DTOs |
| Generator service | `app/Modules/Api/Services/SwaggerGenerateService.php` |
| Controller | `app/Modules/Api/Controllers/SwaggerController.php` |

The generated JSON is the authoritative OpenAPI file. Do not write a separate `openapi.yaml` — L5-Swagger generates it from annotations.

---

## 3. API Surface Overview

### Telegram Webhook (POST)

| Method | Path | Middleware | Description |
|---|---|---|---|
| `POST` | `/api/telegram/bot` | `TelegramQuery` | Receive Telegram webhook events (main bot) |
| `POST` | `/api/telegram/ai/bot` | `TelegramQuery` | Receive Telegram AI bot callback queries (Accept/Cancel/Edit) |
| `GET` | `/api/telegram/set_webhook` | — | Register main bot webhook URL with Telegram |
| `POST` | `/api/ai-bot/webhook` | `AiBotQuery` | Receive AI bot webhook events from Telegram |

### VK Webhook (POST)

| Method | Path | Middleware | Description |
|---|---|---|---|
| `POST` | `/api/vk/bot` | `VkQuery` | Receive VK webhook events |

### Avito Webhook (POST)

| Method | Path | Middleware | Description |
|---|---|---|---|
| `POST` | `/api/avito/bot/{secret?}` | `AvitoQuery` | Receive Avito Messenger webhook events (built-in core module) |

> Auth model differs from Telegram/VK: the secret is embedded in the URL **path segment** (`{secret}`), not a header — Avito faithfully calls back the exact URL registered at subscription time, so a self-chosen path secret is reliable authentication that does not depend on Avito's (undocumented) request-signing scheme. The segment is optional (`{secret?}`) so the route still resolves when no secret is configured — see §4 `AvitoQuery` below. Registered via `php artisan avito:set-webhook`.

### External Traffic (REST)

| Method | Path | Middleware | Description |
|---|---|---|---|
| `GET` | `/api/external/{external_id}/messages` | `ApiQuery` | List messages for external user |
| `GET` | `/api/external/{external_id}/messages/{id_message}` | `ApiQuery` | Get single message |
| `POST` | `/api/external/{external_id}/messages` | `ApiQuery` | Send message from external user |
| `PUT` | `/api/external/{external_id}/messages` | `ApiQuery` | Edit a message |
| `DELETE` | `/api/external/{external_id}/messages` | `ApiQuery` | Delete a message |
| `POST` | `/api/external/{external_id}/files` | `ApiQuery` | Upload a file |

### Widget Gateway

> Auth: `X-Widget-Key: <public_key>` header (no Bearer token). Protected by `WidgetGate` middleware (`app/Modules/External/Middleware/WidgetGate.php`).

| Method | Path | Middleware | Description |
|---|---|---|---|
| `POST` | `/api/widget/{external_id}/messages` | `WidgetGate` | Send a text message from the widget user |
| `POST` | `/api/widget/{external_id}/files` | `WidgetGate` | Send a file from the widget user (multipart, field: `uploaded_file`) |
| `GET` | `/api/widget/{external_id}/messages` | `WidgetGate` | Fetch message history; optional `?after={id}` for incremental polling |
| `OPTIONS` | `/api/widget/{external_id}/{any}` | `WidgetGate` | CORS preflight — returns 204 with CORS headers, no business logic |

#### Request / Response details

**POST /api/widget/{external_id}/messages**
- Body: `{ "text": "string, max 4000 chars" }`
- Response 200: `{ "success": true, "message_id": <int> }`
- Response 422: validation error

**POST /api/widget/{external_id}/files**
- Multipart field: `uploaded_file` (max 20 MB)
- Response 200: `{ "success": true }`
- Response 422: validation error

**GET /api/widget/{external_id}/messages**
- Query: `?after=<int>` (optional — returns only messages with `id > after`; omit for full history)
- Response 200: `{ "messages": [ { "id": int, "text": string|null, "direction": "in"|"out", "created_at": string, "attachments": [] } ] }`

**Error responses**
- 401 — missing or unknown `X-Widget-Key`
- 403 — Origin/IP not in allowlist
- 429 — rate limit exceeded (30/min for send routes, 120/min for polling)

> Widget routes call `ExternalTrafficService` / `ExternalMessageService` / `ExternalFileService` directly — no HTTP loop.
> Widget assets (`public/widget/widget.js`, `style.css`, `manager.png`) are served statically by the web server (no Laravel route).
> Embed: `<script src="https://stand/widget/widget.js" data-domain="https://stand" data-key="pub_xxx" defer></script>`

---

### Files

| Method | Path | Middleware | Description |
|---|---|---|---|
| `GET` | `/api/files/{file_id}` | — | Stream file to client |
| `POST` | `/api/files/{file_id}` | — | Download file |

### Web Routes

| Method | Path | Description |
|---|---|---|
| `GET` | `/` | Main landing page |
| `GET` | `/docs/swagger-v1-json` | OpenAPI JSON |
| `GET` | `/docs/swagger-v1-ui` | Swagger UI |

### Admin Panel (Livewire)

> Auth: session-based, standard Laravel `web` guard. Users are stored in the `users` table. No Filament.
> These routes are custom full-page Livewire/Blade screens — no Swagger annotation required.
> Login form submits via Livewire (`App\Livewire\Auth\LoginPage::authenticate()`), so there is no plain `POST /admin/login` route.

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/admin` | session | Panel root — redirects to `/admin/chats` — name `admin.home` |
| `GET` | `/admin/login` | guest | Login screen (`App\Livewire\Auth\LoginPage`) — name `login` |
| `POST` | `/admin/logout` | session | Log out — name `admin.logout` |
| `GET` | `/admin/chats` | session | Chat workspace (`App\Livewire\Chat\ConversationPage`, custom Livewire full-page) — name `admin.chats` |
| `GET` | `/admin/settings/general` | session | General settings page (`GeneralSettingsPage`, custom Livewire) — name `admin.settings.general` |
| `GET` | `/admin/settings/integrations` | session | Integration channels list (`IntegrationsListPage`, custom Livewire) — name `admin.settings.integrations` |
| `GET` | `/admin/settings/integrations/{channel}` | session | Per-channel config form (`IntegrationChannelPage`; channel ∈ telegram\|telegram_ai\|vk\|max) — name `admin.settings.integrations.channel` |
| `GET` | `/admin/settings/avito` | session (admin only) | Avito module credentials (`AvitoIntegrationPage`) — name `admin.settings.avito`. Always registered (Avito is built-in core, not a conditional package) |
| `GET` | `/admin/settings/ai` | session | AI assistant settings (`AiAssistantPage`, custom Livewire) — name `admin.settings.ai` |
| `GET` | `/admin/settings/ai/{provider}` | session | Per-provider access settings (`AiProviderAccessPage`) — name `admin.settings.ai.provider` |
| `GET` | `/admin/settings/api-webhooks` | session (admin only) | API and webhooks list (`ApiWebhooksPage`, custom Livewire) — name `admin.settings.api-webhooks` |
| `GET` | `/admin/settings/api-webhooks/{source}` | session (admin only) | Per-source config (`ApiWebhookSourcePage`; source ∈ `[0-9]+`) — name `admin.settings.api-webhooks.source` |
| `GET` | `/admin/settings/team` | session (admin only) | Team management screen (`TeamPage`, custom Livewire) — name `admin.settings.team` |
| `GET` | `/admin/bot-user-avatars/{botUser}` | session | Stream locally-stored bot user avatar (fetched by `EnrichBotUserProfileJob`); `avatar_path` must start with `avatars/` — name `admin.bot-user-avatar` |
| `GET` | `/admin/team-member-avatars/{user}` | session | Stream locally-stored team member (operator) avatar (uploaded via Team admin UI); `avatar_path` must start with `avatars/` and contain no `..` — name `admin.team-member-avatar` |

> The legacy Filament resource routes (`/admin/conversations`, `/admin/bot-users`, `/admin/feedbacks`, `/admin/external-sources`) were removed when the admin was rebuilt as custom Livewire screens, and Filament itself was later removed entirely. The underlying models, services, and artisan commands are unchanged.

### Monitoring (Laravel Telescope)

> Debug/inspection dashboard provided by the `laravel/telescope` package (registered automatically).
> Rendered server-side — no Swagger annotation required. Entries stored in the `telescope_entries` tables (PostgreSQL); pruned daily by the scheduled `telescope:prune --hours=48`.

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/telescope` | session (admin only) | Telescope dashboard — requests, exceptions, logs, queries, jobs, cache, redis, events, etc. Path configurable via `TELESCOPE_PATH` |

> Authorization: gated by the middleware stack `['web', 'auth', App\Http\Middleware\TelescopeAccess::class]` (in `config/telescope.php` `middleware`). Guests are redirected to `/admin/login` (302) by the `auth` middleware; authenticated non-admins get **403**; admins reach the dashboard. HTTP Basic auth was removed — its `401` `WWW-Authenticate` challenge is stripped by the upstream edge proxy in front of the production domain, so the login prompt never reached the browser. Independent of `APP_DEBUG` (set `TELESCOPE_ENABLED=false` to disable entirely). See `process/security.md` §7 and `process/observability.md`.

### Telegram callback_data prefixes (main bot webhook)

| Prefix | Handler | Description |
|---|---|---|
| `topic_user_ban_` | `BannedContactMessage::execute()` | Ban / unban user toggle |
| `close_topic` | `CloseTopic::execute()` | Close conversation topic |
| `feedback_rate_{botUserId}_{feedbackId}_{score}` | `HandleFeedbackRating::execute()` | Save user's 1..5 star rating |

### VK / Max callback routing

| Platform | Event type | Payload field | Handler |
|---|---|---|---|
| VK | `message_event` | `payload.command` | `HandleFeedbackRating` when value starts with `feedback_rate_` |
| Max | `message_callback` | `callback.payload` | `HandleFeedbackRating` when value starts with `feedback_rate_` |

> **Avito has no callback routing yet.** Avito Messenger v1 has no inline-keyboard mechanism, so the feedback rating form is sent as plain text and no rating callback ever arrives at `AvitoBotController`. Routing feedback-rating callbacks into `HandleFeedbackRating` is left as a `TODO` in `AvitoBotController` pending confirmation of Avito's callback mechanism.

---

## 4. Middleware Rules

### TelegramQuery
- Validates `X-Telegram-Bot-Api-Secret-Token` header against `TELEGRAM_SECRET_KEY`
- Rejects requests from unauthorized sources with `401`

### VkQuery
- Validates VK secret code from request body against `VK_SECRET_CODE`
- Handles VK confirmation response (`confirm` event type)
- Rejects invalid requests with `403`

### ApiQuery
- Validates `Authorization: Bearer {token}` against `external_source_access_tokens` table
- Only `active = true` tokens are accepted
- Rejects with `401` if token is missing or invalid

### AvitoQuery
- Validates the `{secret}` URL path segment against the `avito.webhook_secret` setting (read via `SettingsService`) using `hash_equals()`
- If `avito.webhook_secret` is empty, the endpoint is left **open** and a warning is logged (`avito_webhook_unverified`) — local development only; a secret must be set in production
- Rejects with `403` on a mismatch
- Logs the incoming webhook body (`avito_request`) before passing the request through

### WidgetGate
- Reads `X-Widget-Key` header; resolves to an `ExternalSource` by `public_key`; rejects with `401` if not found
- Calls `$source->isRequestAllowed($request)` (IP + Origin/Referer host check against `allowed_ips`); rejects with `403` if denied
- Rate limits: 30/min for POST (send) routes, 120/min for GET (poll) routes, keyed by `{public_key}:{client_ip}`
- Sets `Access-Control-Allow-Origin = request Origin` (+ methods/headers) CORS headers on every response
- Handles OPTIONS preflight → 204 (no business logic)
- Attaches resolved `ExternalSource` to `$request->attributes->get('widget_source')`

---

## 5. Request Validation Rules

All external API requests must be validated via Form Request classes.

```php
// ✅ Correct — uses Form Request
class ExternalTrafficRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string'],
            'text' => ['nullable', 'string'],
            'file_id' => ['nullable', 'string'],
        ];
    }
}
```

```php
// ❌ Incorrect — validates manually in controller
public function store(Request $request): void
{
    if (empty($request->input('external_id'))) {
        abort(422);
    }
}
```

---

## 6. Response Format Rules

All JSON responses must follow a consistent envelope format.

```php
// ✅ Correct — consistent JSON response
return response()->json([
    'data' => $result,
], 200);

// Error
return response()->json([
    'message' => 'Not found',
], 404);
```

```php
// ❌ Incorrect — inconsistent or raw response
return response()->json($result);
return response($result, 200);
```

---

## 7. Swagger Annotation Rules

Every public endpoint must have a complete Swagger annotation:

```php
/**
 * @OA\Post(
 *     path="/api/external/{external_id}/messages",
 *     summary="Send message from external user",
 *     tags={"External"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="external_id", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ExternalMessageRequest")),
 *     @OA\Response(response=200, description="Message accepted"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=422, description="Validation error")
 * )
 */
```

Required annotation fields per endpoint:
- `summary`
- `tags`
- `security` (if protected)
- All path/query parameters
- `requestBody` schema (for POST/PUT)
- All response statuses (200, 401, 403, 404, 422)

---

## 8. Security Definitions

All protected endpoints use Bearer token authentication:

```php
/**
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer"
 * )
 */
```

Telegram and VK webhooks use custom header/body validation via middleware (not Bearer tokens).

---

## 9. Change Workflow

Always follow this order when modifying API:

1. Update Swagger annotations in Controller
2. Regenerate Swagger JSON
3. Implement controller logic
4. Update Form Request validation rules
5. Write feature tests

Never implement first.

---

## 10. AI-Specific Rules

- Never invent undocumented routes
- Never change endpoint behavior without updating Swagger annotations
- Never guess request/response schemas
- Always read existing annotations before adding new endpoints
- Treat Swagger as executable specification

---

## 11. Forbidden Behaviors

- ❌ Markdown endpoint descriptions duplicating Swagger
- ❌ Missing security definition on protected endpoints
- ❌ Missing request body schema
- ❌ Missing response statuses
- ❌ Code behavior not matching Swagger spec
- ❌ Endpoint without Form Request validation
- ❌ Returning raw arrays without consistent envelope

---

## Checklist

- [ ] All routes documented in this file
- [ ] All middleware documented with purpose
- [ ] Swagger annotations complete for each endpoint
- [ ] Security defined per route
- [ ] Form Request validation exists for POST/PUT
- [ ] Response format consistent
- [ ] Swagger UI loads successfully
- [ ] No forbidden behaviors
