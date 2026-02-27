# API Endpoints Catalogue

> **Version:** 1.0.0
> **Context:** Read this file before adding, modifying, or consuming any API endpoint.

---

## Webhook endpoints

### POST /api/telegram/bot

**Controller:** `TelegramBotController@bot_query`
**Middleware:** `TelegramQuery`
**Auth required:** Telegram secret header
**Role required:** —

Receives all Telegram webhook updates for the main support bot. Handles `message`, `edited_message`, `callback_query`, `inline_query`, and `chat_member` update types.

#### Request
Raw Telegram Update JSON (sent by Telegram servers).

#### Response `200`
Empty body. Laravel returns 200 automatically.

#### Business rules applied
- BR-001 — Incoming messages are forwarded to the support group
- BR-002 — Outgoing messages are forwarded back to the original platform
- BR-005 — Banned users receive a ban message instead

#### Error responses
| Status | Condition |
|---|---|
| `200` | Always — Telegram requires 200 even on errors (errors logged internally) |

---

### POST /api/telegram/ai/bot

**Controller:** `AiTelegramBotController@bot_query`
**Middleware:** `TelegramQuery`
**Auth required:** Telegram secret header
**Role required:** —

Receives Telegram callback queries for the AI assistant bot (Accept / Cancel / Edit AI message).

#### Request
Raw Telegram callback_query JSON.

#### Response `200`
Empty body.

---

### GET /api/telegram/set_webhook

**Controller:** inline closure
**Middleware:** none
**Auth required:** No
**Role required:** —

Registers the webhook URL with Telegram. Called once during setup. **Do not expose in production without IP restrictions.**

---

### POST /api/vk/bot

**Controller:** `VkBotController@bot_query`
**Middleware:** `VkQuery`
**Auth required:** VK secret code in request body
**Role required:** —

Receives all VK webhook events. Returns VK confirmation code on first call; returns `ok` for all other events.

#### Request
Raw VK Callback API JSON payload.

#### Response `200`
- First call (confirmation): plain text `{VK_CONFIRM_CODE}`
- All subsequent calls: plain text `ok`

---

## External REST API

All endpoints below require `Authorization: Bearer {token}` header.
Base path: `/api/external/{external_id}/`

---

### GET /api/external/{external_id}/messages/

**Controller:** `ExternalTrafficController@index`
**Middleware:** `ApiQuery`
**Auth required:** Bearer token
**Role required:** —

Returns a paginated list of messages for the given external user.

#### Request query parameters
| Parameter | Type | Required | Description |
|---|---|---|---|
| `date_from` | `date` | No | Filter from date (Y-m-d) |
| `date_to` | `date` | No | Filter to date (Y-m-d) |
| `page` | `int` | No | Page number (default 1) |
| `per_page` | `int` | No | Items per page (default 20) |
| `sort` | `string` | No | Sort field |
| `direction` | `string` | No | `asc` or `desc` |

#### Response `200`
```json
{
  "data": [
    {
      "id": 1,
      "message_type": "incoming",
      "text": "Hello",
      "file_id": null,
      "file_type": null,
      "file_name": null,
      "send_status": true,
      "created_at": "2025-01-01T12:00:00Z"
    }
  ],
  "current_page": 1,
  "total": 10
}
```

---

### GET /api/external/{external_id}/messages/{id_message}

**Controller:** `ExternalTrafficController@show`
**Middleware:** `ApiQuery`
**Auth required:** Bearer token
**Role required:** —

Returns a single external message by ID.

#### Response `200`
```json
{
  "id": 1,
  "message_type": "incoming",
  "text": "Hello",
  "file_id": null,
  "file_type": null,
  "file_name": null,
  "send_status": true,
  "created_at": "2025-01-01T12:00:00Z"
}
```

#### Error responses
| Status | Condition |
|---|---|
| `404` | Message not found |
| `401` | Invalid or missing token |

---

### POST /api/external/{external_id}/messages/

**Controller:** `ExternalTrafficController@store`
**Middleware:** `ApiQuery`
**Auth required:** Bearer token
**Role required:** —

Creates a new incoming text message from an external user and forwards it to the Telegram support group.

#### Request
Validated by `ExternalTrafficStoreRequest`.

```json
{
  "text": "Hello, I need help"
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `text` | `string` | Yes | Message text content |

#### Response `201` / `200`
Empty body (dispatches job and returns).

#### Business rules applied
- BR-201 — Token must be valid
- BR-203 — Creates or finds BotUser for external_id

---

### POST /api/external/{external_id}/files/

**Controller:** `ExternalTrafficController@sendFile`
**Middleware:** `ApiQuery`
**Auth required:** Bearer token
**Role required:** —

Uploads a file and creates a message with the file attached.

#### Request
`multipart/form-data`

| Field | Type | Required | Description |
|---|---|---|---|
| `file` | `UploadedFile` | Yes | The file to upload |
| `file_type` | `string` | No | MIME type or category |
| `file_name` | `string` | No | Original file name |

#### Response `200`
Empty body.

---

### PUT /api/external/{external_id}/messages/

**Controller:** `ExternalTrafficController@update`
**Middleware:** `ApiQuery`
**Auth required:** Bearer token
**Role required:** —

Updates the text of an existing external message.

#### Request
Validated by `ExternalTrafficUpdateRequest`.

```json
{
  "message_id": 42,
  "text": "Updated text"
}
```

#### Response `200`
Empty body.

#### Error responses
| Status | Condition |
|---|---|
| `404` | Message not found |
| `422` | Validation failed |

---

### DELETE /api/external/{external_id}/messages/

**Controller:** `ExternalTrafficController@destroy`
**Middleware:** `ApiQuery`
**Auth required:** Bearer token
**Role required:** —

Deletes an external message record.

#### Request
Validated by `ExternalTrafficDestroyRequest`.

```json
{
  "message_id": 42
}
```

#### Response `200` / `204`
Empty body.

---

## File serving endpoints

### GET /api/files/{file_id}

**Controller:** `FilesController@getFileStream`
**Middleware:** none
**Auth required:** No
**Role required:** —

Streams a stored file (e.g. for inline display). `{file_id}` must match `[A-Za-z0-9\-_]+`.

---

### POST /api/files/{file_id}

**Controller:** `FilesController@getFileDownload`
**Middleware:** none
**Auth required:** No
**Role required:** —

Returns a file as a download attachment.

---

## Web routes (non-API)

| Method | Path | Controller | Description |
|---|---|---|---|
| `GET` | `/` | `SimplePage@index` | Home page |
| `GET` | `/live_chat_promo` | `SimplePage@liveChatPromo` | Promo page |
| `GET` | `/preview/chat` | `PreviewController@chat` | Chat preview |
| `GET` | `/docs/swagger-v1-json` | `SwaggerController@showSwagger` | Swagger JSON spec |
| `GET` | `/docs/swagger-v1-ui` | `SwaggerController@swaggerUi` | Swagger UI |

---

## Checklist

- [ ] All routes from `routes/api.php` and `routes/web.php` are listed
- [ ] Every endpoint has auth, middleware, and controller documented
- [ ] Request/response shapes are shown for External API endpoints
- [ ] Error responses are listed for each endpoint
