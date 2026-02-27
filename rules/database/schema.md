# Database Schema

> **Version:** 1.0.0
> **Context:** Read this file before writing any migration, query, or Eloquent relationship. It is the single source of truth for the database structure.

---

## 1. Entity Relationship Diagram

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email UK
        string password
        timestamp email_verified_at
        timestamps
    }

    BOT_USERS {
        bigint id PK
        bigint chat_id
        bigint topic_id "nullable"
        string platform
        bigint external_source_id FK "nullable"
        boolean is_banned
        timestamp banned_at "nullable"
        timestamps
    }

    MESSAGES {
        bigint id PK
        bigint bot_user_id FK
        string platform
        enum message_type "incoming|outgoing"
        bigint from_id
        bigint to_id
        timestamps
    }

    EXTERNAL_MESSAGES {
        bigint id PK
        bigint message_id FK
        text text "nullable"
        text file_id "nullable"
        text file_type "nullable"
        string file_name "nullable"
        boolean send_status "nullable"
        timestamps
    }

    EXTERNAL_SOURCES {
        bigint id PK
        string name UK
        string webhook_url "nullable"
        timestamps
    }

    EXTERNAL_SOURCE_ACCESS_TOKENS {
        bigint id PK
        bigint external_source_id FK
        string token UK "64 chars"
        boolean active
        timestamps
    }

    EXTERNAL_USERS {
        bigint id PK
        text external_id
        string source
        timestamps
    }

    AI_CONDITIONS {
        bigint id PK
        bigint bot_user_id FK
        boolean active
        timestamps
    }

    AI_MESSAGES {
        bigint id PK
        bigint bot_user_id FK
        string message_id
        text text_manager "nullable"
        text text_ai "nullable"
        timestamps
    }

    JOBS {
        bigint id PK
        string queue
        longtext payload
        tinyint attempts
        int reserved_at "nullable"
        int available_at
        int created_at
    }

    FAILED_JOBS {
        bigint id PK
        string uuid UK
        text connection
        text queue
        longtext payload
        longtext exception
        timestamp failed_at
    }

    BOT_USERS ||--o{ MESSAGES : "has many"
    BOT_USERS ||--o| EXTERNAL_USERS : "has one"
    BOT_USERS ||--o| AI_CONDITIONS : "has one"
    BOT_USERS ||--o{ AI_MESSAGES : "has many"
    BOT_USERS }o--o| EXTERNAL_SOURCES : "belongs to"
    MESSAGES ||--o| EXTERNAL_MESSAGES : "has one"
    EXTERNAL_SOURCES ||--o{ EXTERNAL_SOURCE_ACCESS_TOKENS : "has many"
```

---

## 2. Table reference

### `users`
Standard Laravel authentication table for admin/operator users.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `name` | `varchar(255)` | No | — | User display name |
| `email` | `varchar(255)` | No | — | Unique login email |
| `password` | `varchar(255)` | No | — | Bcrypt hash |
| `email_verified_at` | `timestamp` | Yes | NULL | Email verification time |
| `remember_token` | `varchar(100)` | Yes | NULL | Remember-me token |
| `created_at` | `timestamp` | Yes | NULL | — |
| `updated_at` | `timestamp` | Yes | NULL | — |

**Indexes:**
- `PRIMARY` on `id`
- `UNIQUE` on `email`

---

### `bot_users`
End users who communicate with the bot via Telegram or VK.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `chat_id` | `bigint unsigned` | No | — | Platform user ID (Telegram chat_id or VK user_id) |
| `topic_id` | `bigint` | Yes | NULL | Telegram forum topic ID for this user |
| `platform` | `varchar(255)` | No | — | Source platform: `telegram`, `vk`, `external` |
| `external_source_id` | `bigint unsigned` | Yes | NULL | FK to external_sources (for external platform users) |
| `is_banned` | `boolean` | No | `false` | Whether user is banned |
| `banned_at` | `timestamp` | Yes | NULL | When user was banned |
| `created_at` | `timestamp` | Yes | NULL | — |
| `updated_at` | `timestamp` | Yes | NULL | — |

**Indexes:**
- `PRIMARY` on `id`
- `INDEX` on `chat_id`
- `INDEX` on `topic_id`

**Foreign keys:**
- `external_source_id` → `external_sources.id`

---

### `messages`
Every message exchanged through the system (incoming from user or outgoing from operator).

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `bot_user_id` | `bigint unsigned` | No | — | FK to bot_users |
| `platform` | `varchar(255)` | No | — | Platform: `telegram`, `vk`, `external` |
| `message_type` | `enum` | No | — | `incoming` or `outgoing` |
| `from_id` | `bigint` | No | — | Sender's platform message ID |
| `to_id` | `bigint` | No | — | Recipient's platform message ID |
| `created_at` | `timestamp` | Yes | NULL | — |
| `updated_at` | `timestamp` | Yes | NULL | — |

**Indexes:**
- `PRIMARY` on `id`
- `INDEX` on `message_type` (via enum column)

**Foreign keys:**
- `bot_user_id` → `bot_users.id` CASCADE DELETE

---

### `external_messages`
Additional data for messages that involve external API sources.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `message_id` | `bigint unsigned` | No | — | FK to messages |
| `text` | `text` | Yes | NULL | Message text content |
| `file_id` | `text` | Yes | NULL | File identifier (storage path or external ID) |
| `file_type` | `text` | Yes | NULL | MIME type or file category |
| `file_name` | `varchar(255)` | Yes | NULL | Original file name |
| `send_status` | `boolean` | Yes | NULL | Whether message was successfully delivered |
| `created_at` | `timestamp` | Yes | NULL | — |
| `updated_at` | `timestamp` | Yes | NULL | — |

**Foreign keys:**
- `message_id` → `messages.id` CASCADE DELETE

---

### `external_sources`
Registered third-party systems that can send/receive messages via the External API.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `name` | `varchar(255)` | No | — | Unique source identifier (e.g. `crm-system`) |
| `webhook_url` | `varchar(255)` | Yes | NULL | URL to POST outgoing messages to |
| `created_at` | `timestamp` | Yes | NULL | — |
| `updated_at` | `timestamp` | Yes | NULL | — |

**Indexes:**
- `PRIMARY` on `id`
- `UNIQUE` on `name`

---

### `external_source_access_tokens`
API access tokens for external sources. Each source can have multiple tokens.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `external_source_id` | `bigint unsigned` | No | — | FK to external_sources |
| `token` | `varchar(64)` | No | — | Unique API token (hex string) |
| `active` | `boolean` | No | `true` | Whether this token is usable |
| `created_at` | `timestamp` | Yes | NULL | — |
| `updated_at` | `timestamp` | Yes | NULL | — |

**Indexes:**
- `PRIMARY` on `id`
- `UNIQUE` on `token`

**Foreign keys:**
- `external_source_id` → `external_sources.id` CASCADE DELETE

---

### `external_users`
Mapping between external platform user IDs and internal `bot_users`.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `external_id` | `text` | No | — | User ID as given by the external system |
| `source` | `varchar(255)` | No | — | Name of the source system |
| `created_at` | `timestamp` | Yes | NULL | — |
| `updated_at` | `timestamp` | Yes | NULL | — |

---

### `ai_conditions`
Tracks whether the AI assistant is active for a given bot user.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `bot_user_id` | `bigint unsigned` | No | — | FK to bot_users |
| `active` | `boolean` | No | — | Whether AI auto-reply is enabled for this user |
| `created_at` | `timestamp` | Yes | NULL | — |
| `updated_at` | `timestamp` | Yes | NULL | — |

**Foreign keys:**
- `bot_user_id` → `bot_users.id`

---

### `ai_messages`
Stores AI-generated draft responses pending operator approval.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `bot_user_id` | `bigint unsigned` | No | — | FK to bot_users |
| `message_id` | `varchar(255)` | No | — | Platform message ID this draft responds to |
| `text_manager` | `text` | Yes | NULL | Operator's edited version of the AI response |
| `text_ai` | `text` | Yes | NULL | Original AI-generated text |
| `created_at` | `timestamp` | Yes | NULL | — |
| `updated_at` | `timestamp` | Yes | NULL | — |

**Foreign keys:**
- `bot_user_id` → `bot_users.id` CASCADE DELETE

---

### Laravel system tables

**`cache`** / **`cache_locks`** — Laravel cache storage when cache driver is `database`.

**`sessions`** — Laravel session storage when session driver is `database`.

**`jobs`** — Laravel queue job storage when queue driver is `database`.

**`job_batches`** — Laravel job batch tracking.

**`failed_jobs`** — Failed jobs archive with full payload and exception trace.

**`password_reset_tokens`** — Password reset token storage.

---

## 3. Relationship map

- A `BotUser` has many `Messages`.
- A `BotUser` has one `ExternalUser` (when platform is `external`).
- A `BotUser` has one `AiCondition`.
- A `BotUser` has many `AiMessages`.
- A `BotUser` belongs to one `ExternalSource` (optional, via `external_source_id`).
- A `Message` has one `ExternalMessage` (optional, only for external API messages).
- An `ExternalSource` has many `ExternalSourceAccessTokens`.
- An `ExternalSource` has many `BotUsers` through `external_source_id`.

---

## 4. Soft deletes inventory

No tables currently use soft deletes. All deletions are hard deletes with cascade constraints.

---

## 5. Enum values inventory

### `messages.message_type`
| Value | Meaning |
|---|---|
| `incoming` | Message sent by the end user to the bot |
| `outgoing` | Message sent by the operator to the end user |

### `bot_users.platform`
| Value | Meaning |
|---|---|
| `telegram` | User communicates via Telegram private chat |
| `vk` | User communicates via VK |
| `external` | User communicates via a registered external API source |

---

## Checklist

- [ ] ERD diagram reflects current migration state
- [ ] Every table has a row-by-row column description
- [ ] All foreign keys and indexes are documented
- [ ] Relationship map matches Eloquent model definitions
- [ ] Enum values match the application code
