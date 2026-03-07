# Testing Strategy Rules

> **Purpose:** Guarantee that every change is tested adequately before merging. Prevent regressions and silent failures in production.
> **Context:** Read this file before writing any code that affects behavior, APIs, database, or business logic.
> **Version:** 1.0

---

## 1. Core Principle

Test everything that can fail, and fail fast.

- Unit tests for individual components (Actions, Services, Helpers, Models)
- Feature tests for HTTP endpoints and integrations
- Regression tests for previously fixed bugs
- Tests must run before any push (enforced by `pre-push` hook)

---

## 2. Test Structure

```
tests/
├── Unit/                   # Unit tests — isolated, no HTTP, no real DB
│   ├── Actions/
│   │   ├── Telegram/       # Tests for app/Actions/Telegram/*
│   │   ├── Vk/             # Tests for app/Actions/Vk/*
│   │   └── Ai/             # Tests for app/Actions/Ai/*
│   ├── TelegramBot/        # Tests for app/TelegramBot/*
│   ├── VkBot/              # Tests for app/VkBot/*
│   └── (other as needed)
├── Feature/                # Feature tests — HTTP, with database
├── Mocks/                  # Mock objects
├── Stubs/                  # Test data stubs
├── Traits/                 # Reusable test traits
└── TestCase.php
```

---

## 3. Test File Naming Rules

- Test file suffix: `*Test.php`
- Test file location mirrors source file location

| Source file | Test file |
|---|---|
| `app/Actions/Telegram/GetChat.php` | `tests/Unit/Actions/Telegram/GetChatTest.php` |
| `app/Actions/Vk/SendMessageVk.php` | `tests/Unit/Actions/Vk/SendMessageVkTest.php` |
| `app/Actions/Ai/AiAction.php` | `tests/Unit/Actions/Ai/AiActionTest.php` |
| `app/Services/Tg/TgMessageService.php` | `tests/Unit/Services/Tg/TgMessageServiceTest.php` |

```php
// ✅ Correct — mirrors source structure
tests/Unit/Actions/Telegram/GetChatTest.php

// ❌ Incorrect — flat, no structure
tests/Unit/GetChatTest.php
```

---

## 4. Test Naming Rules

Test method names must describe behavior, not implementation.

```php
// ✅ Correct — describes what should happen
public function test_returns_telegram_answer_dto_when_chat_exists(): void {}
public function test_throws_exception_when_invalid_token(): void {}
public function test_can_send_message_to_telegram(): void {}
```

```php
// ❌ Incorrect — describes implementation
public function test_get_chat(): void {}
public function test1(): void {}
```

Naming conventions:
- Snake_case: `test_can_do_something`
- Start with `test_`
- Include the condition and expected outcome

---

## 5. Unit Test Rules

Unit tests must:
- Test a single class (Action, Service, Helper, Model method)
- Mock all external dependencies (HTTP calls, database, queue)
- Be fast and deterministic (no real API calls, no real DB)
- Use `Http::fake()` for Telegram/VK API calls

```php
// ✅ Correct — fakes HTTP for Telegram API call
public function test_get_chat_returns_dto(): void
{
    Http::fake([
        'https://api.telegram.org/*/getChat*' => Http::response([
            'ok' => true,
            'result' => [
                'id' => 12345,
                'type' => 'private',
            ],
        ], 200),
    ]);

    $result = GetChat::execute(12345);

    $this->assertInstanceOf(TelegramAnswerDto::class, $result);
    $this->assertTrue($result->ok);
}
```

```php
// ❌ Incorrect — real API call in unit test
public function test_get_chat(): void
{
    $result = GetChat::execute(12345);  // hits real Telegram API
    $this->assertTrue($result->ok);
}
```

---

## 6. Feature Test Rules

Feature tests must:
- Use `RefreshDatabase` trait to reset database between tests
- Test HTTP endpoints (routes, middleware, controller responses)
- Use factories to create test data
- Test the full request-response cycle

```php
// ✅ Correct — feature test with RefreshDatabase and factory
class ExternalTrafficTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_message_requires_valid_token(): void
    {
        $response = $this->postJson('/api/external/user123/messages', [
            'text' => 'Hello',
        ]);

        $response->assertStatus(401);
    }
}
```

---

## 7. Test Database Configuration

Tests use SQLite in-memory database (configured in `phpunit.xml`):

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="CACHE_STORE" value="array"/>
```

Rules:
- Never use production database in tests
- Use `RefreshDatabase` in Feature tests
- Use factories (`database/factories/`) for test data
- Use `Stubs/` directory for raw data stubs (e.g., Telegram webhook payloads)

---

## 8. Mocking Rules

- Mock Telegram API calls with `Http::fake()`
- Mock VK API calls with `Http::fake()`
- Mock Queue jobs with `Queue::fake()`
- Use `Mockery` for complex dependency mocking
- Store reusable mocks in `tests/Mocks/`

```php
// ✅ Correct — fake queue in feature test
Queue::fake();

$this->postJson('/api/telegram/bot', $webhookPayload);

Queue::assertDispatched(SendTelegramMessageJob::class);
```

---

## 9. Coverage Requirements

- Unit tests: every Action, Service, and Helper class must have a corresponding test file
- New functionality must be covered by tests before merge
- Bug fixes must include a regression test that reproduces the bug

---

## 10. Forbidden Test Patterns

- ❌ Tests that make real HTTP calls to Telegram/VK/AI APIs
- ❌ Tests that rely on production database
- ❌ Tests named `test1`, `testA`, `testFoo`
- ❌ Tests with no assertions (`$this->assertTrue(true)` is meaningless)
- ❌ Skipping tests without a documented reason
- ❌ Tests that depend on execution order

---

## 11. Test Data Rules

- Use `database/factories/` for Eloquent model factories
- Use `tests/Stubs/` for raw webhook payload stubs (JSON arrays)
- Do not hardcode production IDs in tests

```php
// ✅ Correct — factory creates test data
$botUser = BotUser::factory()->create(['platform' => 'telegram']);
```

```php
// ❌ Incorrect — hardcoded production ID
$botUser = BotUser::find(1);
```

---

## 12. Definition of Done

A change is not complete until:

- Unit tests exist for every new Action, Service, Helper class
- Feature tests exist for every new HTTP endpoint
- All tests pass locally (`docker exec -it pet php artisan test`)
- No existing tests broken
- No tests skipped without documented reason

---

## Checklist

- [ ] Unit tests created for new Actions/Services/Helpers
- [ ] Feature tests created for new HTTP endpoints
- [ ] Test file location mirrors source file location
- [ ] Test method names describe behavior
- [ ] `Http::fake()` used for API calls in unit tests
- [ ] `RefreshDatabase` used in Feature tests
- [ ] Factories used for test data
- [ ] All tests pass
- [ ] No real external API calls in tests
- [ ] No forbidden test patterns used
