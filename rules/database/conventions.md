# Database Conventions

> **Version:** 1.0.0
> **Context:** Read this file before writing a migration or modifying the database schema.

---

## 1. Naming conventions

| Object | Pattern | Example |
|---|---|---|
| Tables | `snake_case`, plural | `bot_users`, `external_messages` |
| Columns | `snake_case` | `chat_id`, `external_source_id` |
| Foreign key columns | `{singular_table}_id` | `bot_user_id`, `message_id` |
| Boolean columns | `is_{adjective}` or verb-past-tense | `is_banned`, `active`, `send_status` |
| Timestamp columns | `{event}_at` | `banned_at`, `email_verified_at` |
| Migration files | `{date}_{description}` | `2025_03_23_create_bot_users_table` |

---

## 2. Migration rules

### 2.1 One migration = one logical change

```php
// ✅ Correct — one focused migration
// File: 2025_12_26_add_ban_table.php
Schema::table('bot_users', function (Blueprint $table) {
    $table->boolean('is_banned')->default(false)->after('platform');
    $table->timestamp('banned_at')->nullable()->after('is_banned');
});

// ❌ Incorrect — multiple unrelated changes in one file
Schema::table('bot_users', function (Blueprint $table) {
    $table->boolean('is_banned')->default(false);
    $table->string('preferred_language')->nullable();
    $table->index('topic_id'); // unrelated addition
});
```

### 2.2 New columns must always be nullable or have a default

```php
// ✅ Correct — safe for production tables with existing rows
$table->string('file_name')->nullable()->after('file_type');

// ❌ Incorrect — will fail on a non-empty production table
$table->string('file_name')->after('file_type');
```

### 2.3 Always implement `down()`

See `rules/development/laravel-migrations.md` for the full migration rules. Project-specific additions:
- This project uses PostgreSQL — test migrations locally with `DB_CONNECTION=pgsql`.
- Do not use MySQL-only features like `->after()` in migrations; PostgreSQL ignores column order.

### 2.4 Use `foreignId()->constrained()` shorthand

```php
// ✅ Correct
$table->foreignId('bot_user_id')->constrained('bot_users')->onDelete('cascade');

// ❌ Incorrect — verbose, error-prone
$table->unsignedBigInteger('bot_user_id');
$table->foreign('bot_user_id')->references('id')->on('bot_users');
```

---

## 3. Indexing rules

Add an index for every column used in `WHERE`, `ORDER BY`, `JOIN`, or as a foreign key.

```php
// ✅ Correct — indexes defined in the same migration as the column
$table->unsignedBigInteger('chat_id')->index();
$table->bigInteger('topic_id')->nullable()->index();
```

**Current indexes in this project:**
- `bot_users.chat_id` — used in user lookup by platform ID
- `bot_users.topic_id` — used in reply routing from the support group
- `messages.message_type` — implicit via enum column; add explicit index if filtered frequently

---

## 4. Data types

| Use case | Type to use | Type to avoid |
|---|---|---|
| IDs | `bigint` / `id()` | `int` |
| Platform user IDs | `unsignedBigInteger` | `string` |
| Monetary amounts | `integer` (cents) | `float`, `double`, `decimal` |
| Long text | `text` | `varchar(255)` |
| Flags | `boolean` | `tinyint(1)`, `string` |
| File identifiers | `text` | `varchar(255)` (can be long base64) |
| Timestamps | `timestamp` | `datetime`, `string` |
| API tokens | `string(64)` | larger sizes |

---

## 5. Forbidden practices

- ❌ Running `migrate:fresh` or `migrate:reset` on production.
- ❌ Editing a migration that has already run on any environment — create a new one.
- ❌ Seeding data inside migration files — use seeders or separate data migrations.
- ❌ Storing money as `float` — always use integers (cents).
- ❌ Using MySQL-specific column ordering (`->after()`) without a comment that it is ignored on PostgreSQL.

---

## Checklist

- [ ] Migration file is named following the pattern
- [ ] New columns are nullable or have a default value
- [ ] `down()` reverses everything in `up()`
- [ ] Foreign keys use `foreignId()->constrained()` shorthand
- [ ] Indexes are defined for all filter/sort columns
- [ ] No business logic or data seeding inside the migration
