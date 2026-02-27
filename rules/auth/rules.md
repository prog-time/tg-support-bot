# Authentication & Authorization Rules

> **Version:** 1.0.0
> **Context:** Read this file before modifying middleware, adding new endpoints, or changing token logic.

---

## 1. Authentication mechanisms

This project uses three independent authentication mechanisms, one per API surface:

| Surface | Mechanism | Implementation |
|---|---|---|
| Telegram webhooks | Secret header token | `TelegramQuery` middleware |
| VK webhook | Secret code in request body | `VkQuery` middleware |
| External REST API | Bearer token in Authorization header | `ApiQuery` middleware |

There is **no user-facing authentication** (no login, no sessions, no JWT) for the API. The admin `users` table exists for potential future use.

---

## 2. Telegram webhook authentication (`TelegramQuery`)

Telegram sends a secret token in the `X-Telegram-Bot-Api-Secret-Token` header.

**Rules:**
- The middleware must compare the header value against `TELEGRAM_SECRET_KEY` from the environment.
- If the header is missing or does not match, return HTTP 401 immediately.
- The secret key must be set when registering the webhook with Telegram.

```php
// ✅ Correct — validate the secret header
if ($request->header('X-Telegram-Bot-Api-Secret-Token') !== config('telegram.secret_key')) {
    abort(401);
}

// ❌ Incorrect — skip validation for "simplicity"
// Never bypass webhook signature validation
```

---

## 3. VK webhook authentication (`VkQuery`)

VK sends the secret code configured in the VK app settings as `secret` in the JSON payload.

**Rules:**
- The middleware must compare `$request->input('secret')` against `VK_SECRET_CODE`.
- If it does not match, return HTTP 401.
- Return the VK confirmation code when `type === 'confirmation'` (not a security check — required by VK).

---

## 4. External API authentication (`ApiQuery`)

Third-party systems authenticate using a bearer token.

**Rules:**
- The `Authorization` header must be present and start with `Bearer `.
- The token extracted from the header must exist in `external_source_access_tokens` with `active = true`.
- If the token is missing, malformed, or inactive, return HTTP 401.
- The middleware must identify the `ExternalSource` from the token and make it available to the controller.

```php
// ✅ Correct — validate bearer token against DB
$token = $request->bearerToken();
$record = ExternalSourceAccessTokens::where('token', $token)
    ->where('active', true)
    ->first();

if (! $record) {
    abort(401);
}

// ❌ Incorrect — validate only token format without DB check
if (! $request->bearerToken()) {
    abort(401);
}
```

---

## 5. Token rules

| Property | Value |
|---|---|
| Token length | 64 characters |
| Token format | Hexadecimal string |
| Token storage | `external_source_access_tokens.token` column (plain, not hashed) |
| Token uniqueness | `UNIQUE` constraint on the column |
| Token revocation | Set `active = false` |
| Token expiry | No automatic expiry — manual revocation only |

**Important:** Tokens are stored in plain text in the database. This is acceptable for server-to-server API tokens but means the database must be properly secured.

---

## 6. Role and permission inventory

This project does not currently implement a role/permission system (no Spatie Permission, no Gates, no Policies).

Authorization is entirely based on:
1. Correct webhook secret (Telegram, VK) — grants access to the webhook endpoint
2. Valid active bearer token (External API) — grants access to the External API
3. Platform-level rules (e.g. only messages from the configured `TELEGRAM_GROUP_ID` are treated as operator replies)

---

## 7. Forbidden patterns

- ❌ Hardcoded tokens or secrets in source code — always use `.env` / `config()`.
- ❌ Skipping middleware on webhook routes "for testing" — use a dedicated test environment.
- ❌ Authorization logic inside Models — keep it in middleware and controllers.
- ❌ Sharing the same token between multiple external sources — each source has its own token.
- ❌ Using the plain `Authorization` header value without extracting the bearer token.

```php
// ❌ Incorrect — reading raw header instead of using bearerToken()
$token = $request->header('Authorization');

// ✅ Correct
$token = $request->bearerToken(); // returns the token without "Bearer " prefix
```

---

## Checklist

- [ ] New endpoints have the correct middleware applied
- [ ] Telegram secret key is set in `.env` and registered with Telegram
- [ ] VK secret code matches the VK app settings
- [ ] New external sources have a token created in `external_source_access_tokens`
- [ ] No hardcoded credentials anywhere in source code
