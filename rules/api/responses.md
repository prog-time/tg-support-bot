# API Response Formats

> **Version:** 1.0.0
> **Context:** Read this file before implementing any JSON response, error handler, or API resource.

---

## 1. Webhook responses

Telegram and VK webhook endpoints must return HTTP 200 with an empty body as fast as possible.

```php
// ✅ Correct — return nothing (Laravel returns 200 by default for void controllers)
public function bot_query(): void
{
    (new TgMessageService($this->dataHook))->handle();
}

// ❌ Incorrect — returning a response with data from a webhook handler
public function bot_query(): JsonResponse
{
    return response()->json(['status' => 'ok']);
}
```

**VK exception:** The VK webhook must return the confirmation code as plain text on the first `confirmation` event.

```php
// ✅ Correct VK response
if ($request->input('type') === 'confirmation') {
    return response(config('services.vk.confirm_code'), 200);
}
return response('ok', 200);
```

---

## 2. External API success responses

The External API does not enforce a strict JSON envelope. Responses follow these patterns:

### List response (index)
```json
{
  "data": [...],
  "current_page": 1,
  "per_page": 20,
  "total": 100,
  "last_page": 5
}
```

### Single resource response (show)
```json
{
  "id": 1,
  "message_type": "incoming",
  "text": "Hello",
  "file_id": null,
  "file_type": null,
  "file_name": null,
  "send_status": true,
  "created_at": "2025-01-01T12:00:00Z",
  "updated_at": "2025-01-01T12:00:00Z"
}
```

### Create / update / delete response
Empty body with appropriate HTTP status code (200 or 204).

```php
// ✅ Correct
public function store(): void
{
    $this->externalTrafficService->store();
    // No return — Laravel sends 200 with empty body
}

// ❌ Incorrect — returning meaningless data
public function store(): JsonResponse
{
    $this->externalTrafficService->store();
    return response()->json(['success' => true]);
}
```

---

## 3. Error response format

Laravel's default exception handler produces error responses. The format depends on the route type:

### 401 Unauthorized
```json
{
  "message": "Unauthorized"
}
```

### 403 Forbidden
```json
{
  "message": "Forbidden"
}
```

### 404 Not Found
```json
{
  "message": "Not found"
}
```

### 422 Validation error
```json
{
  "message": "The text field is required.",
  "errors": {
    "text": [
      "The text field is required."
    ]
  }
}
```

### 500 Internal Server Error
```json
{
  "message": "Server Error"
}
```

**Rule:** Never expose stack traces, internal IDs, or raw exception messages to the API consumer.

```php
// ✅ Correct — generic error message
return response()->json(['message' => 'Server Error'], 500);

// ❌ Incorrect — leaking internal details
return response()->json(['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
```

---

## 4. Pagination format

The External API uses Laravel's built-in paginator. The standard format is:

```json
{
  "current_page": 1,
  "data": [...],
  "first_page_url": "...",
  "from": 1,
  "last_page": 5,
  "last_page_url": "...",
  "links": [...],
  "next_page_url": "...",
  "path": "...",
  "per_page": 20,
  "prev_page_url": null,
  "to": 20,
  "total": 100
}
```

---

## 5. Rules — what must never appear in a response

- ❌ Stack traces (`$e->getTraceAsString()`)
- ❌ Raw SQL queries
- ❌ Passwords or password hashes
- ❌ API tokens or secret keys
- ❌ Internal user IDs from other systems
- ❌ Full exception messages in production (`APP_DEBUG=false`)

---

## Checklist

- [ ] Webhook handlers return 200 with empty body
- [ ] VK confirmation returns plain text
- [ ] External API list responses include pagination metadata
- [ ] Error responses never expose internal details
- [ ] All 5 standard error shapes are documented
