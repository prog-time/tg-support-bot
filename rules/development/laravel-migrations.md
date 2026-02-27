## Overview

Laravel migrations are version-controlled database schema changes. These rules ensure that every migration is atomic, reversible, and safe to run in production without downtime. They apply to Laravel 11.x and 12.x with any relational database (MySQL, PostgreSQL, SQLite).

---

## Core Principles

1. **One change per migration** — each file does exactly one logical thing (create a table, add a column, add an index).
2. **Always implement `down()`** — every migration must be fully reversible.
3. **Never modify existing migrations** — once merged to the main branch, a migration is immutable; create a new one instead.
4. **Schema only, no data** — migration files must not contain business logic or data transformations; use seeders or dedicated data migrations for that.
5. **Safe for production** — nullable or default-valued columns, avoid locking operations on large tables.

---

## Naming Conventions

| Operation | Pattern | Example |
|---|---|---|
| Create table | `create_{table}_table` | `create_orders_table` |
| Add column | `add_{column}_to_{table}_table` | `add_status_to_orders_table` |
| Add multiple columns | `add_{context}_columns_to_{table}_table` | `add_address_columns_to_users_table` |
| Remove column | `remove_{column}_from_{table}_table` | `remove_legacy_field_from_users_table` |
| Rename column | `rename_{old}_to_{new}_in_{table}_table` | `rename_name_to_full_name_in_users_table` |
| Add index | `add_{column}_index_to_{table}_table` | `add_email_index_to_users_table` |
| Drop table | `drop_{table}_table` | `drop_legacy_logs_table` |
| Modify column | `change_{column}_in_{table}_table` | `change_description_in_products_table` |

Generate via Artisan — do not create migration files manually:

```bash
php artisan make:migration create_orders_table
php artisan make:migration add_status_to_orders_table --table=orders
```

---

## Creating Tables

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();                                        // BIGINT UNSIGNED AUTO INCREMENT
            $table->foreignId('user_id')->constrained();        // FK → users.id with constraint
            $table->string('number', 32)->unique();
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->unsignedInteger('total_cents');             // money as integer, never float
            $table->text('notes')->nullable();
            $table->timestamps();                               // created_at + updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

**Table rules:**
- Always use `return new class extends Migration` (anonymous class, Laravel 9+).
- Always define `$table->id()` as primary key unless there is a clear reason not to.
- Always define `$table->timestamps()` unless the table is a pivot.
- Use `$table->softDeletes()` when records must be recoverable.
- Store monetary values as integers (cents), never `FLOAT` or `DOUBLE`.

---

## Adding Columns

```php
public function up(): void
{
    Schema::table('orders', function (Blueprint $table) {
        // Add after a specific column for readability (MySQL only)
        $table->string('tracking_number', 64)->nullable()->after('number');
    });
}

public function down(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->dropColumn('tracking_number');
    });
}
```

**Rules:**
- New columns on existing tables **must** be `nullable()` or have a `default()` — otherwise the migration fails on non-empty tables.
- Drop the column in `down()` in the reverse order of creation when multiple columns are added.
- Never use `->change()` without the `doctrine/dbal` package installed (Laravel < 11); in Laravel 11+ native column modification is available.

---

## Foreign Keys

```php
// ✅ Preferred — shorthand with constraint
$table->foreignId('user_id')->constrained();

// ✅ Custom table or column name
$table->foreignId('author_id')->constrained('users');

// ✅ With cascade
$table->foreignId('user_id')->constrained()->cascadeOnDelete();

// ❌ Avoid — verbose and error-prone
$table->unsignedBigInteger('user_id');
$table->foreign('user_id')->references('id')->on('users');
```

Drop foreign keys in `down()` before dropping the column:

```php
public function down(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->dropForeign(['user_id']);
        $table->dropColumn('user_id');
    });
}
```

---

## Indexes

Add indexes for every column used in `WHERE`, `ORDER BY`, or `JOIN` conditions:

```php
public function up(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->index('status');                            // single-column
        $table->index(['user_id', 'status']);              // composite — order matters
        $table->index(['created_at']);                     // for date range queries
    });
}

public function down(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->dropIndex(['user_id', 'status']);
        $table->dropIndex(['status']);
        $table->dropIndex(['created_at']);
    });
}
```

**Index rules:**
- Put the highest-cardinality column first in composite indexes.
- Do not over-index write-heavy tables — indexes slow down `INSERT` and `UPDATE`.
- Unique constraints double as unique indexes: `$table->unique('email')`.
- For full-text search use `$table->fullText('body')` (MySQL / PostgreSQL).

---

## Pivot Tables

```php
Schema::create('role_user', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->primary(['user_id', 'role_id']);    // composite PK — no auto-increment id
    // No timestamps unless you need to know when the relation was created
});
```

- Name pivot tables with the two model names in **alphabetical order**, snake_case, singular: `role_user`, not `user_roles`.
- Use a composite primary key instead of an auto-increment `id` unless the pivot has its own model.

---

## Anti-patterns

### Business logic in migrations

```php
// ❌ Never — fragile, ties schema to application state
public function up(): void
{
    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('key')->unique();
        $table->text('value');
        $table->timestamps();
    });

    // Seeding inside a migration breaks rollback and test isolation
    DB::table('settings')->insert([
        ['key' => 'app_name', 'value' => 'MyApp'],
    ]);
}

// ✅ Correct — seed data belongs in DatabaseSeeder or a dedicated data migration
```

### Missing `down()`

```php
// ❌ Never leave down() empty — makes rollback impossible
public function down(): void
{
    //
}

// ✅ Always undo exactly what up() did
public function down(): void
{
    Schema::dropIfExists('orders');
}
```

### Modifying deployed migrations

```php
// ❌ Never edit a migration that has already run on any environment
// This breaks the migration state for every developer and every server.
// Create a new migration instead.
```

### Non-nullable column on a populated table

```php
// ❌ Fails in production if the table already has rows
$table->string('phone');

// ✅ Safe — existing rows get NULL, add NOT NULL constraint later with a data migration
$table->string('phone')->nullable();
```

---

## Security Checklist

- [ ] No raw user input used in migration logic.
- [ ] No secrets or credentials hardcoded in migration files.
- [ ] Foreign key constraints are defined — orphaned rows are prevented at the DB level.
- [ ] Sensitive columns (`password`, `token`, `secret`) use appropriate length and are never stored as plain text.
- [ ] Indexes added for columns used in authentication queries (`email`, `api_token`).

---

## Running Migrations

```bash
# Run all pending migrations
php artisan migrate

# Preview SQL without running (Laravel 11+)
php artisan migrate --pretend

# Rollback the last batch
php artisan migrate:rollback

# Rollback N batches
php artisan migrate:rollback --step=2

# Rebuild the database (drop all + re-migrate) — development only
php artisan migrate:fresh

# Rebuild with seeders
php artisan migrate:fresh --seed
```

**Never run `migrate:fresh` or `migrate:reset` on a production database.**

---

## References

- [Laravel Migrations](https://laravel.com/docs/12.x/migrations)
- [Laravel Schema Builder](https://laravel.com/docs/12.x/migrations#creating-tables)
- [Available Column Types](https://laravel.com/docs/12.x/migrations#available-column-types)
