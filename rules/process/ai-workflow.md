# AI Workflow Rules

> **Purpose:** Explain that these rules constrain AI behavior to prevent unsafe or low-quality changes and to make results deterministic across agents.
> **Context:** Read this file before performing any modification, refactor, migration, or feature implementation.
> **Version:** 1.0

---

## 1. Core Principle

The agent must behave like a careful mid-level engineer, not an auto-generator.

- Always analyze before coding
- Always design before implementing
- Always test before finishing
- Never generate large changes blindly
- Never modify files you did not read

---

## 2. Mandatory Development Lifecycle

Follow these steps strictly and in order.

### Step 1 — Understand

- Read `rules/README.md`
- Read relevant `domain/*.md` files for the affected domain
- Read related code files before proposing changes
- Identify existing patterns to follow (Actions, Services, Jobs, DTOs)
- Check `rules/database/schema.md` if touching data layer

### Step 2 — Design

- Propose architecture or schema changes first
- List impacted files with their roles
- Identify risks and edge cases
- Define method signatures before implementation
- Do not write implementation code yet

### Step 3 — Implement

- Make the smallest possible change to achieve the goal
- Follow existing conventions exactly (PSR-12 + Laravel style)
- Reuse existing abstractions (Actions, Services, DTOs)
- Avoid introducing new patterns unless absolutely required
- Generate one module at a time, review before continuing

### Step 4 — Verify

- Run: `docker exec -it pet ./vendor/bin/pint` (code formatting)
- Run: `docker exec -it pet ./vendor/bin/phpstan analyse` (static analysis, level 6)
- Run: `docker exec -it pet php artisan test` (all tests)
- Fix all PHPStan errors before proceeding
- Do not skip pre-commit hooks

### Step 5 — Document

- Update `rules/*.md` files if behavior, schema, or API changed
- Add PHPDoc to all new public methods
- Update `rules/database/schema.md` if migrations were added
- Update `rules/api/endpoints.md` if routes were changed

The task is not complete until documentation is updated.

---

## 3. Code Style Rules

This project uses **PSR-12 + Laravel** conventions enforced by **Laravel Pint**.

```php
// ✅ Correct — PSR-12 compliant
public function execute(BotUser $botUser, TelegramUpdateDto $dto): void
{
    $result = SendTelegramMessageJob::dispatch($botUser, $dto);
}
```

```php
// ❌ Incorrect — wrong indentation, missing return type
public function execute($botUser,$dto){
  $result=SendTelegramMessageJob::dispatch($botUser,$dto);
}
```

Rules enforced by Pint:
- 4-space indentation
- Single quotes for strings
- Short array syntax `[]`
- Trailing comma in multiline arrays
- Unused imports removed
- Imports sorted alphabetically

---

## 4. PHPDoc Rules

Required for all public methods.

```php
// ✅ Correct — complete PHPDoc
/**
 * Send banned notification to a Telegram user.
 *
 * @param BotUser $botUser The banned user to notify
 * @return void
 * @throws TelegramException When Telegram API call fails
 */
public static function execute(BotUser $botUser): void
{
}
```

```php
// ❌ Incorrect — missing PHPDoc
public static function execute(BotUser $botUser): void
{
}
```

---

## 5. Scope Control Rules

- Only modify files directly related to the task
- Never refactor unrelated code opportunistically
- Never introduce "drive-by improvements"
- Large refactors must be separate tasks with separate design steps

```php
// ✅ Correct — minimal targeted change
public function cancel(Order $order): void
{
    $order->update(['status' => 'cancelled']);
}
```

```php
// ❌ Incorrect — mixes refactor with feature
public function cancel(Order $order)
{
    // rewrote repository, renamed services, changed architecture,
    // and also cancelled the order
}
```

---

## 6. Generation Size Limits

- Prefer small iterations over large outputs
- Never generate more than one module at a time
- Never create more than 300–500 lines of new code without review
- Break large features into subtasks

Reason: large generations increase hallucination risk and reduce review quality.

---

## 7. Safety Rules

- Never invent database columns that do not exist in `rules/database/schema.md`
- Never invent API fields not defined in Swagger
- Never guess business logic — check `domain/*.md` files
- If unsure, search the codebase before assuming
- Do not fabricate PHP packages or dependencies

```php
// ✅ Correct — uses existing field from schema
$botUser->topic_id

// ❌ Incorrect — hallucinated field
$botUser->thread_id   // does not exist
```

---

## 8. Commit Rules

```
issues-{number} | {brief description}
```

Examples:
- `issues-42 | add support for VK audio messages`
- `issues-85 | fix topic recreation on banned user reply`
- `issues-101 | update AI session timeout logic`

Change type prefixes:
- `add` — new feature
- `fix` — bug fix
- `update` — update existing functionality
- `refactor` — refactoring without behavior change
- `remove` — deletion
- `docs` — documentation only
- `test` — tests only
- `style` — formatting only
- `chore` — routine maintenance

---

## 9. Test-First Expectations

- Add or update tests for every behavior change
- Bug fixes must include regression tests
- New features must include: happy path + edge cases + error cases
- Do not rely only on manual reasoning — tests are the proof

---

## 10. Documentation Coupling Rules

Every code change must update rules.
Failure to update documentation means the task is incomplete.

| Code Change | Documentation Update Required |
|---|---|
| New migration | `rules/database/schema.md` |
| New endpoint | `rules/api/endpoints.md` |
| New business rule | Relevant `rules/domain/*.md` |
| New job | `rules/domain/messaging.md` (if messaging-related) |
| Changed architecture | `rules/process/architecture-design.md` |

---

## 11. Forbidden Behaviors

- ❌ Generating entire applications from scratch without incremental review
- ❌ Editing files without reading them first
- ❌ Ignoring existing PSR-12/Laravel conventions
- ❌ Silent schema changes (must update schema.md)
- ❌ Skipping tests
- ❌ Skipping documentation updates
- ❌ Making speculative optimizations not related to the task
- ❌ Using `--no-verify` to bypass git hooks

---

## Checklist

- [ ] Relevant `rules/` files read
- [ ] Existing code inspected before implementation
- [ ] Design proposed before implementation
- [ ] Minimal change applied
- [ ] Tests added or updated
- [ ] Laravel Pint formatting passes
- [ ] PHPStan level 6 passes
- [ ] PHPDoc added to public methods
- [ ] Documentation updated in `rules/`
- [ ] No hallucinated fields or APIs introduced
