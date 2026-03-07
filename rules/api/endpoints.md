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
| Generator service | `app/Services/Swagger/SwaggerGenerateService.php` |
| Controller | `app/Http/Controllers/SwaggerController.php` |

The generated JSON is the authoritative OpenAPI file. Do not write a separate `openapi.yaml` — L5-Swagger generates it from annotations.

---

## 3. API Surface Overview

### Telegram Webhook (POST)

| Method | Path | Middleware | Description |
|---|---|---|---|
| `POST` | `/api/telegram/bot` | `TelegramQuery` | Receive Telegram webhook events |
| `POST` | `/api/telegram/ai/bot` | `TelegramQuery` | Receive Telegram AI bot webhook events |
| `GET` | `/api/telegram/set_webhook` | — | Register webhook URL with Telegram |

### VK Webhook (POST)

| Method | Path | Middleware | Description |
|---|---|---|---|
| `POST` | `/api/vk/bot` | `VkQuery` | Receive VK webhook events |

### External Traffic (REST)

| Method | Path | Middleware | Description |
|---|---|---|---|
| `GET` | `/api/external/{external_id}/messages` | `ApiQuery` | List messages for external user |
| `GET` | `/api/external/{external_id}/messages/{id_message}` | `ApiQuery` | Get single message |
| `POST` | `/api/external/{external_id}/messages` | `ApiQuery` | Send message from external user |
| `PUT` | `/api/external/{external_id}/messages` | `ApiQuery` | Edit a message |
| `DELETE` | `/api/external/{external_id}/messages` | `ApiQuery` | Delete a message |
| `POST` | `/api/external/{external_id}/files` | `ApiQuery` | Upload a file |

### Files

| Method | Path | Middleware | Description |
|---|---|---|---|
| `GET` | `/api/files/{file_id}` | — | Stream file to client |
| `POST` | `/api/files/{file_id}` | — | Download file |

### Web Routes

| Method | Path | Description |
|---|---|---|
| `GET` | `/` | Main landing page |
| `GET` | `/live_chat_promo` | Live chat promo page |
| `GET` | `/preview/chat` | Chat widget preview |
| `GET` | `/docs/swagger-v1-json` | OpenAPI JSON |
| `GET` | `/docs/swagger-v1-ui` | Swagger UI |

### Admin Panel (Filament 3)

> Auth: session-based. Users are stored in the `users` table. Managed by Filament auth.
> These routes are rendered server-side by Filament/Livewire — no Swagger annotation required.

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/admin` | session | Admin panel home (redirects to first resource) |
| `GET` | `/admin/login` | — | Login form |
| `POST` | `/admin/login` | — | Authenticate manager |
| `POST` | `/admin/logout` | session | Log out |
| `GET` | `/admin/conversations` | session | List all conversations (`ConversationResource`) |
| `GET` | `/admin/conversations/{id}` | session | View conversation with message history (`ViewConversation`) |
| `GET` | `/admin/bot-users` | session | List all bot users (`BotUserResource`) |
| `GET` | `/admin/external-sources` | session | List external sources (`ExternalSourceResource`) |
| `GET` | `/admin/external-sources/create` | session | Create external source form |
| `GET` | `/admin/external-sources/{id}/edit` | session | Edit external source form |

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
