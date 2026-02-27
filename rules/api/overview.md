# API Design Principles

> **Version:** 1.0.0
> **Context:** Read this file before adding or modifying any API endpoint.

---

## 1. API surface overview

This application exposes three distinct API surfaces:

| Surface | Base path | Auth | Purpose |
|---|---|---|---|
| Telegram webhooks | `/api/telegram/bot` | Secret header | Receive Telegram updates |
| VK webhook | `/api/vk/bot` | VK secret code | Receive VK updates |
| External REST API | `/api/external/{id}/...` | Bearer token | Third-party system integration |

There is no public user-facing API and no session-based API. The admin panel (if any) uses web routes with standard Laravel session auth.

---

## 2. Design rules

### 2.1 Controllers are thin

```php
// ✅ Correct — controller delegates everything to a service
public function store(): void
{
    (new ExternalTrafficService($this->dataHook))->store();
}

// ❌ Incorrect — business logic in controller
public function store(Request $request): JsonResponse
{
    $botUser = BotUser::firstOrCreate([...]);
    $message = Message::create([...]);
    Http::post($webhookUrl, $message->toArray());
}
```

### 2.2 Request validation uses Form Request classes

```php
// ✅ Correct — validation in a FormRequest
class ExternalTrafficStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return ['text' => 'required|string|max:4096'];
    }
}

// ❌ Incorrect — inline validation in controller
public function store(Request $request): void
{
    $request->validate(['text' => 'required|string']);
}
```

### 2.3 Webhook endpoints return quickly

Telegram and VK expect a 200 response within a few seconds. All processing must be dispatched to a Job.

```php
// ✅ Correct — dispatch and return immediately
public function bot_query(): void
{
    (new TgMessageService($this->dataHook))->handle();
    // handle() dispatches a Job internally — no return value needed
}

// ❌ Incorrect — synchronous external API call in controller
public function bot_query(): void
{
    TelegramMethods::sendQueryTelegram('sendMessage', [...]);
}
```

### 2.4 API versioning

This project does not currently use URL-based API versioning (`/api/v1/`). All routes are under `/api/`. If versioning is needed in future, document the decision in `architecture/overview.md`.

### 2.5 Swagger documentation

Every External API endpoint must have `@OA\` (OpenAPI) annotations. Run `docker exec -it pet php artisan l5-swagger:generate` to regenerate docs.

```php
// ✅ Correct — Swagger annotation on controller method
/**
 * @OA\Post(
 *     path="/api/external/{external_id}/messages/",
 *     ...
 * )
 */
public function store(): void { ... }
```

---

## 3. HTTP status codes used

| Code | When used |
|---|---|
| `200 OK` | Successful GET or update |
| `201 Created` | Resource successfully created (where applicable) |
| `204 No Content` | Successful delete or action with no response body |
| `401 Unauthorized` | Missing or invalid API token |
| `403 Forbidden` | Valid token but insufficient permissions |
| `404 Not Found` | Resource does not exist |
| `422 Unprocessable Entity` | Validation failed |
| `500 Internal Server Error` | Unexpected application error |

---

## 4. Middleware inventory

| Middleware | Applied to | Purpose |
|---|---|---|
| `TelegramQuery` | `/api/telegram/bot`, `/api/telegram/ai/bot` | Validates Telegram `X-Telegram-Bot-Api-Secret-Token` header |
| `VkQuery` | `/api/vk/bot` | Validates VK secret code in the request body |
| `ApiQuery` | `/api/external/{id}/...` | Validates `Authorization: Bearer {token}` header |

---

## Checklist

- [ ] All new endpoints use a Service class — no business logic in controller
- [ ] Validation is in a FormRequest class
- [ ] Webhook handlers dispatch Jobs and return immediately
- [ ] Swagger annotations are added for External API endpoints
- [ ] Correct HTTP status codes are used
