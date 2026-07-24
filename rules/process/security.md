# Security Rules

> **Purpose:** Ensure every change is secure by default. Prevent common classes of vulnerabilities frequently introduced by AI-generated code (injection, broken auth, leaked secrets, unsafe defaults).
> **Context:** Read this file before implementing any feature that touches authentication, input handling, database queries, external APIs, or configuration.
> **Version:** 1.0

---

## 1. Core Principle

Security is not optional or deferred.

- Always secure by default
- Always validate external input
- Always enforce authorization explicitly
- Never trust generated code blindly
- Never assume a route or service is internal-only

---

## 2. Webhook Authentication Rules

### Telegram Webhooks

All Telegram requests must be validated by the appropriate middleware before the controller runs.

**Main bot** — `TelegramQuery` middleware:
- Validates `X-Telegram-Bot-Api-Secret-Token` header against the `telegram.secret_key` setting (read via `SettingsService`; stored in the `settings` DB table)
- Rejects invalid requests with `403`
- Applies to: `POST /api/telegram/bot` and `POST /api/telegram/ai/bot`

**AI bot** — `AiBotQuery` middleware (`app/Modules/Ai/Middleware/AiBotQuery.php`):
- Validates `X-Telegram-Bot-Api-Secret-Token` header against the `telegram_ai.secret` setting (read via `SettingsService`)
- Rejects missing or invalid tokens with `403`
- Applies to: `POST /api/ai-bot/webhook`

```php
// ✅ Correct — each bot has its own middleware and secret
Route::post('/api/telegram/bot', [TelegramBotController::class, 'bot_query'])
    ->middleware(TelegramQuery::class);

Route::post('/api/ai-bot/webhook', [AiBotController::class, 'handle'])
    ->middleware(AiBotQuery::class);
```

```php
// ❌ Incorrect — unprotected webhook endpoint
Route::post('/api/ai-bot/webhook', [AiBotController::class, 'handle']);
```

### VK Webhooks

All VK requests must be validated by `VkQuery` middleware:

- Validates secret code from request body against the `vk.secret_key` setting (read via `SettingsService`)
- Returns confirmation code for VK verification requests
- Rejects invalid requests with `403`

### Avito Webhooks

Avito requests are validated by `AvitoQuery` middleware (`app/Modules/Avito/Middleware/AvitoQuery.php`) — **different scheme from Telegram/VK**: the secret travels in the URL **path**, not a header or body field.

- Route: `POST /api/avito/bot/{secret?}` — `{secret}` is a self-chosen value (not issued by Avito), compared against the `avito.webhook_secret` setting (read via `SettingsService`) using `hash_equals()`
- Rationale: Avito faithfully calls back the exact URL registered at subscription time (via `php artisan avito:set-webhook`), so embedding a secret in the path is reliable authentication that does not depend on Avito's own (undocumented) request-signing scheme
- **If `avito.webhook_secret` is empty, the endpoint is left open** and a warning is logged (`avito_webhook_unverified`) — acceptable only for local development; a secret must be configured before exposing the endpoint publicly
- Rejects a mismatched secret with `403`

```php
// ✅ Correct — each bot/platform has its own middleware and secret
Route::post('/api/avito/bot/{secret?}', [AvitoBotController::class, 'bot_query'])
    ->middleware(AvitoQuery::class);
```

### External API

All External API requests must be validated by `ApiQuery` middleware:

- Validates `Authorization: Bearer {token}` against `external_source_access_tokens` table
- Only `active = true` tokens are accepted
- Rejects missing or invalid tokens with `401`

---

## 3. Input Validation Rules

All external input is untrusted.

Applies to:
- HTTP requests (Telegram webhooks, VK webhooks, External REST API)
- Queue payloads
- File uploads

**Mandatory rules:**
- Validate all inputs at the boundary (Form Request or DTO `fromRequest()`)
- Use DTOs to type incoming data before passing to services
- Never pass raw `Request` objects to Services or Actions
- Reject unexpected fields
- Validate file types before storing

```php
// ✅ Correct — DTO validates and types incoming data
$dto = TelegramUpdateDto::fromRequest($request);
$this->service->process($dto);
```

```php
// ❌ Incorrect — raw request passed to service
$this->service->process($request);
```

---

## 4. Database Safety Rules

- Always use Eloquent ORM or Laravel's query builder with parameter binding
- Never concatenate user input into raw SQL
- Never use `DB::statement()` with user-provided values

```php
// ✅ Correct — parameterized query via Eloquent
BotUser::where('chat_id', $chatId)
    ->where('platform', $platform)
    ->first();
```

```php
// ❌ Incorrect — SQL injection risk
DB::select("SELECT * FROM bot_users WHERE chat_id = '$chatId'");
```

---

## 5. Secrets Management Rules

- **Application access credentials (bot tokens, webhook secrets, AI provider keys) live in the DB `settings` table, NOT in `.env`/`config()`.** They are read via `SettingsService`, stored encrypted (`Crypt::encrypt()`) for `is_secret` keys, and edited in `/admin/settings/*`. There is no `config()` fallback for these keys (`config => null` in `SettingKeyRegistry`).
- Only **infrastructure** secrets remain in environment variables (`.env`): `APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD`, `AWS_*`, `TG_LOGGER_TOKEN`. Never commit `.env`.
- Never hardcode API keys, tokens, or passwords in code or config files
- Never echo secrets in logs (see also `process/observability.md`)

```php
// ✅ Correct — read application credentials from the DB settings layer
$token = app(\App\Services\Settings\SettingsService::class)->get('telegram.token');
```

```php
// ❌ Incorrect — reading an app credential from config()/env() (removed), or hardcoding
$token = config('traffic_source.settings.telegram.token'); // no longer exists
$token = '1234567890:AABBcc_my_telegram_token_here';        // hardcoded secret
```

**Application access secrets (DB `settings` table, via `SettingsService`):**
- `telegram.token`, `telegram.secret_key` — main bot token + webhook validation secret
- `telegram_ai.token`, `telegram_ai.secret` — AI bot token + webhook validation secret
- `vk.token`, `vk.secret_key`, `vk.confirm_code` — VK API token + webhook secret + confirm code
- `max.token`, `max.secret_key` — MAX token + webhook secret
- `avito.client_secret` — Avito OAuth client secret; `avito.webhook_secret` — self-chosen secret embedded in the webhook URL path (see AvitoQuery above). Built-in module, no `.env`/`config()` fallback (`config => null`), same as every other channel
- `ai.openai_api_key`, `ai.deepseek_client_secret`, `ai.gigachat_client_secret` (+ client ids / base urls / models / cert) — AI providers
- Non-secret access keys (`telegram.group_id`, `telegram_ai.username`, `avito.client_id`, `avito.user_id`, `avito.base_url`, etc.) also live in `settings` but are stored unencrypted

**Infrastructure secrets (`.env` only):** `APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD`, `AWS_*`, `TG_LOGGER_TOKEN`. Per-source bearer tokens live in the `external_source_access_tokens` table.

**Handling rules for DB settings secrets:**
- Secrets (`is_secret = true` in `SettingKeyRegistry`) are encrypted via `Crypt::encrypt()` and surfaced in the admin UI as `<input type="password">` fields
- Never log decrypted token values — log only non-sensitive context (URL registered, HTTP status code)
- Blank-submission guard: if the UI field is left empty, do NOT overwrite the stored encrypted value
- In logs, do not include any key whose `is_secret = true` in `SettingKeyRegistry`
- Read credentials via `SettingsService::get()` — never via `config()`/`env()` (the access branches were removed)

---

## 6. File Upload Security Rules

- Validate file type before storing (do not trust `Content-Type` header alone)
- Store uploaded files in `storage/app/` (not in `public/`)
- Serve files through `FilesController` which controls access
- Never expose raw file paths in API responses

```php
// ✅ Correct — file served via controller with access control
GET /api/files/{file_id}  → FilesController@getFileStream
```

```php
// ❌ Incorrect — direct public URL to stored file
return response()->json(['url' => 'storage/uploads/user_file.jpg']);
```

---

## 7. Authorization Rules

- Every protected route must go through appropriate middleware (`TelegramQuery`, `VkQuery`, `ApiQuery`)
- The External API uses token-based authorization (`ApiQuery` middleware)
- Admin routes (if any) must require authenticated session
- **Settings role gate:** `/admin/settings/*` is restricted by role via `EnsureSettingsAccess` middleware (`app/Modules/Admin/Middleware/`), applied to the settings route group after the standard `auth` middleware. Admins (`UserRole::Admin`) reach every settings screen; managers (`UserRole::Manager`) may open only «Основные» (`admin.settings.general`) and are redirected there from any other settings route. The «Основные» config form additionally hides its admin-only card in the view and `GeneralSettingsPage::save()` refuses non-admins (defence against crafted Livewire calls). See `rules/domain/admin-panel.md` BR-008 / BR-029.
- The **Telescope dashboard** (`GET /telescope`) is gated by session-based admin auth — middleware stack `['web', 'auth', App\Http\Middleware\TelescopeAccess::class]` (in `config/telescope.php` `middleware`): guests are redirected to `/admin/login` (**302**), authenticated non-admins get **403**, admins reach the dashboard. This is **independent of `APP_DEBUG`** (`TELESCOPE_ENABLED=false` disables it entirely). HTTP Basic auth was removed because the upstream edge proxy in front of the production domain intercepts `4xx` responses and strips the `401` `WWW-Authenticate` challenge, so the Basic-auth prompt never reached the browser; session auth (`2xx`/`3xx`) passes the edge. See `process/observability.md` for the full notes.
- Never implement authorization logic inside Services or Models — use middleware and policies

```php
// ✅ Correct — authorization in middleware
class ApiQuery
{
    public function handle(Request $request, \Closure $next): mixed
    {
        $token = $request->bearerToken();
        if (!ExternalSourceAccessTokens::where('token', $token)->where('active', true)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}
```

```php
// ❌ Incorrect — inline auth check in service
class ExternalTrafficService
{
    public function store(Request $request): void
    {
        if ($request->header('Authorization') !== 'Bearer my-token') {
            abort(401);  // auth logic in service
        }
    }
}
```

---

## 8. AI-Specific Safety Rules

These rules exist specifically for AI agents:

- Never invent security mechanisms
- Never guess cryptography implementations
- Never design custom auth or crypto
- Use established Laravel mechanisms only (middleware, Eloquent, Hash facade)
- Never fabricate fields like `isAdmin`, `isVerified`, `isSecure` that don't exist in the schema
- If unsure about a security decision, stop and ask instead of guessing

Hallucinated security logic is dangerous.

---

## 9. Error Response Rules

- Never return stack traces to clients
- Never expose internal IDs, file paths, or debug info in API responses
- Use generic error messages for security-sensitive failures

```php
// ✅ Correct
return response()->json(['message' => 'Unauthorized'], 401);
```

```php
// ❌ Incorrect
return response()->json([
    'error' => 'Unauthorized',
    'trace' => $e->getTraceAsString(),
    'token' => $bearerToken,
], 401);
```

---

## 10. Dependency Security Rules

- All dependencies are locked via `composer.lock`
- Never add packages without explicit justification
- Prefer packages with active maintenance history
- Do not upgrade packages without testing

---

## 11. TrustProxies

`bootstrap/app.php` sets `->trustProxies(at: '*')`. This means `$request->ip()` returns the value from the `X-Forwarded-For` header rather than the raw socket IP.

**Trade-off:** trusting all proxies means a client could spoof `X-Forwarded-For` in a direct connection (bypassing IP allowlists). This is acceptable because the server is always deployed behind a trusted reverse proxy (nginx). If the deployment changes to direct public exposure, switch `at: '*'` to an explicit proxy IP list.

**Impact on widget gateway:** `WidgetGate` uses `$request->ip()` for the rate-limit key and IP allowlist checks. As long as the nginx proxy is in front, the resolved IP is reliable. Do not remove `->trustProxies()` without auditing every place that calls `$request->ip()`.

---

## 12. Widget Public Key

The `public_key` on `ExternalSource` is **intentionally public** — it appears in browser-embedded `<script>` tags. It identifies the source but does NOT grant admin or management access.

- Do not encrypt or treat as a secret (no `is_secret` flag needed)
- Never log it (same policy as all tokens, to prevent cross-referencing)
- Rate limiting (30/min send, 120/min poll) and origin/IP allowlist checking in `WidgetGate` are the primary abuse-prevention controls
- Rotating the key immediately invalidates all active widget sessions for that source; rotation is a deliberate admin action

---

## 13. Widget externalId (v1 risk)

Widget `externalId` is client-generated (stored in `localStorage`) and is **not HMAC-signed in v1**. A client who discovers another client's `externalId` could read or write to their conversation thread.

**Accepted v1 risk.** Mitigation in place: rate limiting and origin allowlist in `WidgetGate` reduce casual abuse. HMAC-signed session tokens are planned for v2. Do not reference this as a security guarantee in user-facing docs.

---

## Forbidden Behaviors

- ❌ Unprotected webhook endpoints
- ❌ Raw SQL string concatenation
- ❌ Hardcoded credentials
- ❌ Tokens or passwords in log output
- ❌ Raw `Request` objects passed to business logic
- ❌ Catching and silently ignoring security errors
- ❌ Custom cryptography implementations
- ❌ Returning debug information in API error responses

---

## Checklist

- [ ] All webhook routes use appropriate middleware
- [ ] All External API routes use `ApiQuery` middleware
- [ ] Input validated via DTO `fromRequest()` or Form Request
- [ ] No raw SQL with user input
- [ ] No secrets in code or config files
- [ ] Files served via controller, not public URLs
- [ ] Authorization enforced via middleware
- [ ] No sensitive data in error responses
- [ ] No invented security mechanisms
