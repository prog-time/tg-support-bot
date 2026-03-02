# Observability Rules

> **Purpose:** Guarantee that every feature is observable in production. Prevent "black box" systems that cannot be debugged after deployment.
> **Context:** Read this file before implementing any endpoint, background job, integration, or infrastructure change.
> **Version:** 1.0

---

## 1. Core Principle

If you cannot observe it, you cannot operate it.

- Every flow must produce logs
- Every service must expose health signals
- Every critical action must be measurable
- Every failure must be traceable
- Silent failures are forbidden

---

## 2. Monitoring Stack

This project uses the following observability tools:

| Tool | Purpose | Access |
|---|---|---|
| **Loki** | Centralized log aggregation | Port 3100 |
| **Grafana** | Log visualization, dashboards | Port 3000 |
| **Promtail** | Log forwarder (reads nginx + app logs) | — |
| **Sentry** | Error tracking, exception capture | `SENTRY_LARAVEL_DSN` |
| **TG Logger** (`prog-time/tg-logger`) | Send critical alerts to Telegram channel | `TG_LOGGER_TOKEN`, `TG_LOGGER_CHAT_ID` |

---

## 3. Structured Logging Rules

Logs must be structured and machine-readable.

**Mandatory rules:**
- Use Laravel's `Log` facade (backed by Monolog)
- Use channel-specific loggers configured in `config/logging.php`
- Include context array with relevant identifiers
- Include severity level appropriate to the event
- Never log sensitive data (tokens, passwords, secrets)

```php
// ✅ Correct — structured log with context
Log::info('Message sent to Telegram', [
    'bot_user_id' => $botUser->id,
    'chat_id' => $botUser->chat_id,
    'message_type' => $dto->typeQuery,
]);
```

```php
// ❌ Incorrect — unstructured and untrackable
Log::info('Message sent');
echo 'Message sent';
```

---

## 4. Log Level Conventions

| Level | When to Use | Example |
|---|---|---|
| `debug` | Development diagnostics only | DTO fields dump |
| `info` | Business events (created, sent, received) | "Topic created for user" |
| `warning` | Recoverable issues, retries | "Telegram API rate limit, retrying" |
| `error` | Failures that affect functionality | "Failed to send message after 5 retries" |
| `critical` | System cannot continue, data loss risk | "Job queue exhausted" |

**Rules:**
- Do not log everything as `error`
- Do not log business events as `debug`
- Production must not rely on `debug` logs

---

## 5. Telegram API Error Logging

All `TelegramError` cases must be logged explicitly.

```php
// ✅ Correct — log Telegram errors with context
$error = TelegramError::fromResponse($answer->description);
Log::error('Telegram API error', [
    'error' => $error?->value,
    'description' => $answer->description,
    'bot_user_id' => $botUser->id,
    'method' => $dto->methodQuery,
]);
```

```php
// ❌ Incorrect — swallow error silently
$error = TelegramError::fromResponse($answer->description);
// do nothing
```

---

## 6. Job Observability Rules

Every queue Job must include:

- Log at job start (INFO level, with job data context)
- Log at job completion (INFO level)
- Log on failure (ERROR level, with exception message)
- Exception must be re-thrown after logging (not swallowed)

```php
// ✅ Correct — job with proper logging
class SendTelegramMessageJob implements ShouldQueue
{
    public function handle(): void
    {
        Log::info('SendTelegramMessageJob started', ['bot_user_id' => $this->botUser->id]);

        try {
            // ... send logic
            Log::info('SendTelegramMessageJob completed', ['bot_user_id' => $this->botUser->id]);
        } catch (\Exception $e) {
            Log::error('SendTelegramMessageJob failed', [
                'bot_user_id' => $this->botUser->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

```php
// ❌ Incorrect — silent catch
public function handle(): void
{
    try {
        // ... send logic
    } catch (\Exception $e) {
        // nothing logged, exception swallowed
    }
}
```

---

## 7. Sentry Integration Rules

- Sentry DSN is configured via `SENTRY_LARAVEL_DSN`
- All unhandled exceptions are automatically captured by Sentry
- Do not log sensitive user data in Sentry context
- `SENTRY_TRACES_SAMPLE_RATE` controls performance monitoring sampling (default: 0.1 = 10%)

```php
// ✅ Correct — Sentry captures context for debugging
\Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($botUser): void {
    $scope->setUser(['id' => $botUser->id, 'platform' => $botUser->platform]);
});
```

```php
// ❌ Incorrect — logging sensitive data
\Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($dto): void {
    $scope->setExtra('raw_data', $dto->rawData);  // may contain user tokens/messages
});
```

---

## 8. Health Check Rules

The application must remain operable when checked by Docker/orchestration.

- PHP-FPM health is managed by Docker (`docker/php-fpm/` config)
- Nginx health is managed by Docker (`docker/nginx/` config)
- PostgreSQL connectivity is verified on app startup
- Redis connectivity is verified on queue worker startup
- Do not add heavy database queries to health check endpoints

---

## 9. Sensitive Data Rules

Never expose private information in logs or error messages.

Forbidden in logs:
- `TELEGRAM_TOKEN`
- `VK_TOKEN`
- Bearer tokens from `external_source_access_tokens`
- User passwords
- AI provider API keys (`OPENAI_API_KEY`, `GIGACHAT_CLIENT_SECRET`, etc.)
- Full webhook payloads (may contain PII)

Mask when necessary:

```php
// ✅ Correct — mask token in log
Log::info('Webhook sent', ['url' => $webhookUrl, 'token' => '***']);
```

---

## 10. Definition of Done for Observability

A feature is not complete unless:

- Logs added for main flows (start, success, failure)
- Errors logged at correct level with context
- No silent catch blocks
- Sentry will capture unhandled exceptions automatically
- Sensitive data is not present in any log output

Observability is part of implementation, not an afterthought.

---

## Forbidden Behaviors

- ❌ `echo`, `var_dump`, `print_r` in production code
- ❌ Silent catch blocks (`catch (\Exception $e) {}`)
- ❌ Logging entire request/response objects without filtering
- ❌ Logging tokens or passwords
- ❌ Using `debug` level for production-critical events
- ❌ Relying only on manual debugging

---

## Checklist

- [ ] Structured logs used with context arrays
- [ ] Proper log levels applied
- [ ] Job start/success/failure logged
- [ ] Telegram API errors logged with context
- [ ] Sentry DSN configured
- [ ] No sensitive data in logs
- [ ] No silent failures
- [ ] New flows observable in production
