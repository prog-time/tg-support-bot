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
        string role
        string avatar_path
        string password
        timestamp email_verified_at
        string remember_token
        timestamps
    }

    BOT_USERS {
        bigint id PK
        string chat_id
        bigint topic_id
        string platform
        string display_name
        string username
        string avatar_path
        timestamp profile_synced_at
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
        bigint sender_user_id FK "nullable"
        string sender_name "nullable"
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
        string public_key UK "nullable"
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
        string status
        text text_manager
        text text_ai
        timestamps
    }

    FEEDBACKS {
        bigint id PK
        bigint bot_user_id FK
        smallint rating
        text comment
        string status
        timestamp closed_at
        timestamps
    }

    SETTINGS {
        bigint id PK
        string key UK
        text value
        string type
        boolean is_secret
        timestamps
    }

    BOT_USERS ||--o{ MESSAGES : "has many"
    USERS ||--o{ MESSAGES : "sender (nullable)"
    MESSAGES ||--o| EXTERNAL_MESSAGES : "has one"
    BOT_USERS ||--o| EXTERNAL_USERS : "has one"
    BOT_USERS ||--o| AI_CONDITIONS : "has one"
    BOT_USERS ||--o{ AI_MESSAGES : "has many"
    BOT_USERS ||--o{ FEEDBACKS : "has many"
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
| `role` | `string` | No | `manager` | Access role: `admin` or `manager` |
| `avatar_path` | `string` | Yes | NULL | Local storage path on the `local` disk (e.g. `avatars/user-42.jpg`) — uploaded via the Team admin UI; NULL = use deterministic initials |
| `email_verified_at` | `timestamp` | Yes | NULL | Email verification timestamp |
| `password` | `string` | No | — | Hashed password (bcrypt) |
| `remember_token` | `string(100)` | Yes | NULL | Session remember token |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- UNIQUE on `email` — prevents duplicate accounts

**Migration:** `database/migrations/2026_06_15_000002_add_avatar_path_to_users_table.php`

---

### `bot_users`

Core table. Stores every user that has interacted with the bot across all platforms.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `chat_id` | `string` | No | — | User's ID in Telegram, VK, Max, Avito, or External system. Widened from `unsignedBigInteger` to `string` (migration `2026_07_06_000001_change_bot_users_chat_id_to_string.php`) so platforms with non-numeric ids — e.g. Avito's `u2i-...` format — can be stored natively; numeric platforms still store their id as text |
| `topic_id` | `bigint` | Yes | NULL | Telegram forum topic ID for this user's conversation |
| `platform` | `string` | No | — | Platform: `telegram`, `vk`, `max`, `avito`, `external_source` |
| `display_name` | `string` | Yes | NULL | Human-readable display name (first + last name or username fallback) — populated by `EnrichBotUserProfileJob` and sync'd from webhook on Telegram |
| `username` | `string` | Yes | NULL | Platform handle/username (e.g. Telegram @username) — populated from DTO or async job |
| `avatar_path` | `string` | Yes | NULL | Local storage path on the `local` disk under `avatars/` (e.g. `avatars/bot-user-42.jpg`) — populated by `EnrichBotUserProfileJob` |
| `profile_synced_at` | `timestamp` | Yes | NULL | Last time profile data was fetched from the platform API; used as a 30-day TTL guard in `EnrichBotUserProfileJob` |
| `external_source_id` | `string` | Yes | NULL | External source identifier (for `external_source` platform) |
| `is_banned` | `boolean` | No | `false` | Whether user is banned from sending messages |
| `banned_at` | `timestamp` | Yes | NULL | When the user was banned |
| `is_closed` | `boolean` | No | `false` | Whether the conversation is closed |
| `closed_at` | `timestamp` | Yes | NULL | When the conversation was closed |
| `manager_last_read_at` | `timestamp` | Yes | NULL | When a manager last opened the dialog in the chat workspace; drives the unread indicator (BR-003e) |
| `created_at` | `timestamp` | Yes | NULL | First interaction time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- INDEX on `chat_id` — used in all user lookup queries
- INDEX on `topic_id` — used in all topic-based lookups

**Migration:** `database/migrations/2026_07_06_000001_change_bot_users_chat_id_to_string.php` — widens `chat_id` to `string`. **Irreversible once any non-numeric `chat_id` exists** (e.g. an Avito `u2i-...` id) — the `down()` migration casts back to `unsignedBigInteger`, which fails once a non-numeric value is present.

**Enums:**

`bot_users.platform`
- `telegram` — user interacts via Telegram
- `vk` — user interacts via VK
- `max` — user interacts via Max
- `avito` — user interacts via Avito Messenger (built-in core module, text-only in v1)
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
| `sender_user_id` | `bigint` | Yes | NULL | FK → `users.id` (nullOnDelete) — admin-panel operator who sent this outgoing message; null for incoming, AI auto-replies, and telegram-group replies |
| `sender_name` | `string` | Yes | NULL | Name snapshot of the operator at send time — survives operator deletion; null when no author is recorded |
| `created_at` | `timestamp` | Yes | NULL | Message time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- INDEX on `message_type` — used in filtering queries
- FOREIGN KEY `bot_user_id` → `bot_users.id` ON DELETE CASCADE
- FOREIGN KEY `sender_user_id` → `users.id` ON DELETE SET NULL

**Migration:** `database/migrations/2026_06_15_000003_add_sender_to_messages_table.php`

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
| `allowed_ips` | `json` | Yes | NULL | Allowlist of IPs the API accepts requests from; NULL/empty = no restriction (enforced by `ApiQuery` middleware) |
| `public_key` | `varchar` | Yes | NULL | Low-privilege public key for the widget gateway. Separate from bearer tokens in `external_source_access_tokens`. Generated via `ExternalSourceTokensService::generatePublicKey()` (`pub_` prefix + 36 random chars). Rotatable from the API Webhooks admin page. NULL means no widget key has been assigned yet. |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- UNIQUE on `name` — prevents duplicate source names
- UNIQUE on `public_key` — enforces one key per source; nullable to allow sources without a widget key

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
| `message_id` | `string` | Yes | NULL | Telegram message ID of the AI draft in the group; NULL when created in admin_panel mode |
| `status` | `string` | No | `pending` | Lifecycle status: `pending` (awaiting review), `accepted` (sent to user), `cancelled` (discarded) |
| `text_manager` | `text` | Yes | NULL | Instructions provided by the manager to AI |
| `text_ai` | `text` | Yes | NULL | AI-generated response text |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- FOREIGN KEY `bot_user_id` → `bot_users.id` ON DELETE CASCADE

**Migrations:**
- `database/migrations/2026_06_16_000001_add_status_to_ai_messages_table.php` — adds `status` column (default `pending`) and makes `message_id` nullable

---

### `feedbacks`

Post-close feedback records. Created when `CloseTopic::execute()` closes a conversation. One record per close event — history accumulates.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `bot_user_id` | `bigint` | No | — | FK → `bot_users.id` (cascade delete) |
| `rating` | `smallint` | Yes | NULL | User's rating 1..5; NULL until the user rates |
| `comment` | `text` | Yes | NULL | Optional text comment (reserved for future use) |
| `status` | `string` | No | `awaiting_rating` | Lifecycle status (see enum below) |
| `closed_at` | `timestamp` | Yes | NULL | Timestamp of the conversation close that triggered this record |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- FOREIGN KEY `bot_user_id` → `bot_users.id` ON DELETE CASCADE

**Enums:**

`feedbacks.status`
- `awaiting_rating` — rating form was sent; user has not yet tapped a star
- `completed_no_comment` — user submitted a rating; comment column is NULL

**Migration:** `database/migrations/2026_05_20_000001_create_feedbacks_table.php`

---

### `settings`

Persistent key-value store for runtime-editable application configuration. Created by the Settings Persistence Layer (issue #150). Admin-panel UI for editing these values is implemented in dependent issues #144/#145/#146.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `key` | `string` | No | — | Dot-notation setting key (e.g. `app.manager_interface`, `telegram.token`) — unique |
| `value` | `text` | Yes | NULL | Stored value; plain text for non-secrets, Laravel `Crypt::encrypt()` output for secrets |
| `type` | `string` | No | `string` | PHP type for coercion when reading: `string` \| `bool` \| `int` \| `json` |
| `is_secret` | `boolean` | No | `false` | When `true`, `value` is encrypted by `SettingsService`; readable in plain text only via `SettingsService::get()` |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`
- UNIQUE on `key` — one row per setting key

**Reading priority:** `SettingsService::get($key)` reads: DB row → `config()`/`.env` default (defined in `SettingKeyRegistry`) → `null`.

**Known keys and types** are declared in `app/Services/Settings/SettingKeyRegistry.php`. Unknown keys are accepted but default to `type=string`, no config fallback, `is_secret=false`.

**Encryption:** `SettingsService` calls `Crypt::encrypt()` before writing and `Crypt::decrypt()` after reading for keys where `is_secret=true`. The `value` column stores the raw encrypted string — do not read it directly; always go through `SettingsService`.

**DB stays empty by default.** No seed data is written at first deploy. `SettingsService::get()` falls back to `config()`/`.env` for every unknown key, so the app works normally with an empty `settings` table. Values enter the DB only when saved from the admin panel.

**Migration:** `database/migrations/2026_05_29_000001_create_settings_table.php`

---

### `auto_replies`

Auto-reply rules: a trigger phrase and the response sent when it matches. Managed from the admin panel at `/admin/settings/auto-replies` (`App\Livewire\Settings\AutoRepliesPage` + `AutoReplyFormPage`).

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | No | auto | Primary key |
| `trigger` | `string` | No | — | Trigger word/phrase that activates the auto-reply |
| `response` | `text` | No | — | Response text sent when the trigger matches |
| `enabled` | `boolean` | No | `true` | Whether the rule is active |
| `created_at` | `timestamp` | Yes | NULL | Creation time |
| `updated_at` | `timestamp` | Yes | NULL | Last update time |

**Indexes:**
- PRIMARY on `id`

**Model:** `App\Models\AutoReply` (`enabled` cast to `boolean`).

**Seeder:** `Database\Seeders\AutoReplySeeder` (called from `DatabaseSeeder`) inserts four demo rules only when the table is empty.

**Note:** Matching the trigger against incoming messages and sending the response is NOT yet wired into the message pipeline — the table and admin CRUD exist, runtime triggering is a separate future task.

**Migration:** `database/migrations/2026_06_05_000001_create_auto_replies_table.php`

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
