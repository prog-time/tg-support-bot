# Database Schema

> **Purpose:** This file defines the complete database schema. It ensures that AI agents and developers fully understand data structure, relationships, and constraints before making changes.
> **Context:** Read this file before creating or modifying tables, columns, indexes, migrations, or Eloquent models.
> **Version:** 1.0

---

## 1. Core Principle

The database is the source of truth.

- Every table must be documented
- Every column must be explained
- Every relationship must be explicit
- Hidden or implicit behavior is forbidden
- Schema changes without documentation are forbidden

---

## 2. ERD (Entity Relationship Diagram)

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email UK
        string password
        timestamp email_verified_at
        string remember_token
        timestamps
    }

    BOT_USERS {
        bigint id PK
        bigint chat_id
        bigint topic_id
        string platform
        string external_source_id
        boolean is_banned
        timestamp banned_at
        timestamps
    }

    MESSAGES {
        bigint id PK
        bigint bot_user_id FK
        string platform
        enum message_type
        bigint from_id
        bigint to_id
        timestamps
    }

    EXTERNAL_MESSAGES {
        bigint id PK
        bigint message_id FK
        text text
        text file_id
        text file_type
        string file_name
        boolean send_status
        timestamps
    }

    EXTERNAL_USERS {
        bigint id PK
        text external_id
        string source
        timestamps
    }

    EXTERNAL_SOURCES {
        bigint id PK
        string name UK
        string webhook_url
        timestamps
    }

    EXTERNAL_SOURCE_ACCESS_TOKENS {
        bigint id PK
        bigint external_source_id FK
        string token UK
        boolean active
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
        text text_manager
        text text_ai
        timestamps
    }

    BOT_USERS ||--o{ MESSAGES : "has many"
    MESSAGES ||--o| EXTERNAL_MESSAGES : "has one"
    BOT_USERS ||--o| EXTERNAL_USERS : "has one"
    BOT_USERS ||--o| AI_CONDITIONS : "has one"
    BOT_USERS ||--o{ AI_MESSAGES : "has many"
    EXTERNAL_SOURCES ||--o{ EXTERNAL_SOURCE_ACCESS_TOKENS : "has many"
```

---

## 3. Table Documentation

### `users`

Laravel authentication table for admin users.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `name` | `string` | No | — | Display name |
| `email` | `string` | No | — | Unique login identifier |
| `email_verified_at` | `timestamp` | Yes | NULL | Email verification timestamp |
| `password` | `string` | No | — | Hashed password (bcrypt) |
| `remember_token` | `string(100)` | Yes | NULL | Session remember token |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- UNIQUE on `email` — prevents duplicate accounts

---

### `bot_users`

Core table. Stores every user that has interacted with the bot across all platforms.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `chat_id` | `bigint` | No | — | User's ID in Telegram, VK, or External system |
| `topic_id` | `bigint` | Yes | NULL | Telegram forum topic ID for this user's conversation |
| `platform` | `string` | No | — | Platform: `telegram`, `vk`, `external_source` |
| `external_source_id` | `string` | Yes | NULL | External source identifier (for `external_source` platform) |
| `is_banned` | `boolean` | No | `false` | Whether user is banned from sending messages |
| `banned_at` | `timestamp` | Yes | NULL | When the user was banned |
| `created_at` | `timestamp` | Yes | NULL | First interaction time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- INDEX on `chat_id` — used in all user lookup queries
- INDEX on `topic_id` — used in all topic-based lookups

**Enums:**

`bot_users.platform`
- `telegram` — user interacts via Telegram
- `vk` — user interacts via VK
- `external_source` — user interacts via External API

---

### `messages`

Tracks all individual messages exchanged between users and the support team.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `bot_user_id` | `bigint` | No | — | FK → `bot_users.id` (cascade delete) |
| `platform` | `string` | No | — | Platform of the message |
| `message_type` | `enum` | No | — | Direction: `incoming` or `outgoing` |
| `from_id` | `bigint` | No | — | Sender's ID |
| `to_id` | `bigint` | No | — | Recipient's ID |
| `created_at` | `timestamp` | Yes | NULL | Message time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- INDEX on `message_type` — used in filtering queries
- FOREIGN KEY `bot_user_id` → `bot_users.id` ON DELETE CASCADE

**Enums:**

`messages.message_type`
- `incoming` — message sent by the user (to the support team)
- `outgoing` — message sent by the support team (to the user)

---

### `external_messages`

Additional data for messages originating from External Sources (file info, text, delivery status).

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `message_id` | `bigint` | No | — | FK → `messages.id` (cascade delete) |
| `text` | `text` | Yes | NULL | Message text content |
| `file_id` | `text` | Yes | NULL | File identifier (Telegram file_id or URL) |
| `file_type` | `text` | Yes | NULL | MIME type or type label (photo, document, audio) |
| `file_name` | `string` | Yes | NULL | Original file name |
| `send_status` | `boolean` | Yes | NULL | Whether the message was delivered: true/false/NULL (unknown) |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- FOREIGN KEY `message_id` → `messages.id` ON DELETE CASCADE

---

### `external_users`

Stores user identities from External Source systems.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `external_id` | `text` | No | — | User's ID in the external system |
| `source` | `string` | No | — | Name of the external source (matches `external_sources.name`) |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`

---

### `external_sources`

Registry of all external integrations. Each source has a unique name and optional webhook.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `name` | `string` | No | — | Unique source identifier |
| `webhook_url` | `string` | Yes | NULL | URL to call when the support team sends a reply |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- UNIQUE on `name` — prevents duplicate source names

---

### `external_source_access_tokens`

Bearer tokens used to authenticate requests from External Sources.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `external_source_id` | `bigint` | No | — | FK → `external_sources.id` (cascade delete) |
| `token` | `string(64)` | No | — | Unique bearer token value |
| `active` | `boolean` | No | `true` | Whether this token is currently valid |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- UNIQUE on `token` — prevents token collisions
- FOREIGN KEY `external_source_id` → `external_sources.id` ON DELETE CASCADE

---

### `ai_conditions`

Tracks whether the AI assistant is active for a given bot user conversation.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `bot_user_id` | `bigint` | No | — | FK → `bot_users.id` |
| `active` | `boolean` | No | — | Whether AI is currently handling this user |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- FOREIGN KEY `bot_user_id` → `bot_users.id`

---

### `ai_messages`

Stores AI-generated draft responses before manager review.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `bot_user_id` | `bigint` | No | — | FK → `bot_users.id` (cascade delete) |
| `message_id` | `string` | No | — | Telegram message ID of the AI draft in the group |
| `text_manager` | `text` | Yes | NULL | Instructions provided by the manager to AI |
| `text_ai` | `text` | Yes | NULL | AI-generated response text |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- FOREIGN KEY `bot_user_id` → `bot_users.id` ON DELETE CASCADE

---

### `jobs`, `job_batches`, `failed_jobs` (Laravel)

Laravel queue tables. Do not modify manually.

---

### `cache`, `cache_locks` (Laravel)

Laravel cache tables when using database cache driver.

---

## 4. Change Management Rules

Schema updates must follow this strict order:

1. Update ERD in this file
2. Update table documentation
3. Update enum documentation
4. Write migration file
5. Update Eloquent model (`app/Models/`)
6. Write tests

Never merge migrations without documentation updates.

---

## 5. Migration Location

All migrations are in `database/migrations/`. Name format: `YYYY_MM_DD_HHMMSS_description.php`.

---

## Checklist

- [ ] ERD diagram present and correct
- [ ] Every table documented
- [ ] Every column documented with type, nullable, default, description
- [ ] Indexes explained with purpose
- [ ] Soft deletes justified (not used in this project — hard deletes with cascade)
- [ ] Enums fully listed with value meanings
- [ ] Docs updated before writing migration
- [ ] No forbidden behaviors
