# Testing Rules

> **Version:** 1.0.0
> **Context:** Read this file before writing or modifying any test in this project. Also read `rules/development/laravel_tests.md` for general Laravel testing conventions.

---

## 1. Test structure

```
tests/
├── Feature/          # HTTP endpoint and integration tests
├── Unit/             # Service, Action, Helper, DTO unit tests
│   ├── Actions/
│   ├── Services/
│   └── Helpers/
├── Mocks/            # Typed mock DTOs (pre-built test data)
│   ├── Tg/           # TelegramUpdateDto mocks
│   ├── Vk/           # VkUpdateDto / VkAnswerDto mocks
│   └── External/     # ExternalMessageDto mocks
└── Stubs/            # Service stubs for dependency replacement
    ├── Services/
    └── Tg/
```

**Mapping rule:** Every production class in `app/` must have a corresponding test in `tests/Unit/` or `tests/Feature/`.

```
app/Actions/Telegram/SendContactMessage.php
→ tests/Unit/Actions/Telegram/SendContactMessageTest.php

app/Services/Tg/TgMessageService.php
→ tests/Unit/Services/Tg/TgMessageServiceTest.php
```

---

## 2. Mock classes

The project provides pre-built mock DTOs for common test scenarios. Use them instead of constructing raw data manually.

| Mock class | Location | What it provides |
|---|---|---|
| `TelegramUpdateDtoMock` | `tests/Mocks/Tg/` | A standard private Telegram message update |
| `TelegramUpdateDto_VKMock` | `tests/Mocks/Tg/` | A Telegram update originating from a VK platform user |
| `TelegramUpdateDto_ExternalMock` | `tests/Mocks/Tg/` | A Telegram update from an external platform user |
| `TelegramUpdateDto_GroupMock` | `tests/Mocks/Tg/` | A Telegram update from the support group (supergroup) |
| `TelegramUpdate_AiButtonAction` | `tests/Mocks/Tg/` | A callback_query update for AI Accept/Cancel buttons |
| `TelegramAnswerDtoMock` | `tests/Mocks/Tg/` | A successful Telegram API response |
| `VkUpdateDtoMock` | `tests/Mocks/Vk/` | A standard VK message update |
| `VkAnswerDtoMock` | `tests/Mocks/Vk/` | A VK API response |
| `ExternalMessageDtoMock` | `tests/Mocks/External/` | An incoming external message |
| `ExternalMessageAnswerDtoMock` | `tests/Mocks/External/` | An external API answer |
| `ExternalMessageResponseDtoMock` | `tests/Mocks/External/` | An external API response |

```php
// ✅ Correct — use pre-built mock
$dto = TelegramUpdateDtoMock::make();
$service = new TgMessageService($dto);

// ❌ Incorrect — constructing raw DTO data manually in every test
$dto = new TelegramUpdateDto(
    updateId: 123456,
    typeQuery: 'message',
    // ... 15 more fields
);
```

---

## 3. Project-specific forbidden patterns

Based on past experience and the project's architecture:

- ❌ **Never call `TelegramMethods::sendQueryTelegram()` in a test without mocking** — this will make real API calls.
- ❌ **Never test jobs by running them synchronously without `Queue::fake()`** unless you are specifically testing job internals.
- ❌ **Never create a `BotUser` manually with `new BotUser()`** — always use `BotUser::factory()` or the model's static factory methods.
- ❌ **Never assert on specific Telegram message IDs** — they are assigned by Telegram and will differ between environments.
- ❌ **Never hardcode `TELEGRAM_GROUP_ID` or `TELEGRAM_TOKEN`** in tests — use `config()` or mock the config.

```php
// ✅ Correct — mock external API calls
$this->mock(TelegramMethods::class, function ($mock) {
    $mock->shouldReceive('sendQueryTelegram')->once()->andReturn(TelegramAnswerDtoMock::make());
});

// ❌ Incorrect — real API call in test
TelegramMethods::sendQueryTelegram('sendMessage', [...]);
```

---

## 4. Testing webhook controllers

Feature tests for webhook controllers must:
1. Mock the middleware or provide valid headers.
2. Use `Queue::fake()` to intercept job dispatches.
3. Assert the correct job was dispatched, not the actual delivery.

```php
// ✅ Correct — test that the correct job is dispatched
#[Test]
public function incoming_telegram_message_dispatches_send_job(): void
{
    // Arrange
    Queue::fake();
    $payload = TelegramUpdateDtoMock::toArray();

    // Act
    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', config('telegram.secret_key'))
        ->postJson('/api/telegram/bot', $payload);

    // Assert
    Queue::assertPushed(SendTelegramMessageJob::class);
}
```

---

## 5. Testing Services

Service tests are unit tests. They must:
- Instantiate the service with mock dependencies.
- Use mock DTOs from `tests/Mocks/`.
- Assert the correct jobs are dispatched and correct models are created/updated.

```php
// ✅ Correct
#[Test]
public function handle_creates_bot_user_and_dispatches_job(): void
{
    // Arrange
    Queue::fake();
    $dto = TelegramUpdateDtoMock::make();

    // Act
    (new TgMessageService($dto))->handle();

    // Assert
    Queue::assertPushed(SendTelegramMessageJob::class);
    $this->assertDatabaseHas('bot_users', ['chat_id' => $dto->chatId]);
}
```

---

## 6. Seeding rules

- Seeders must not be used in tests — use factories for all test data.
- `DatabaseSeeder` is for local development setup only.
- The `RefreshDatabase` trait is the standard for all Feature tests.

---

## 7. Running tests

```bash
# All tests
docker exec -it pet php artisan test

# Specific test
docker exec -it pet php artisan test --filter=TestClassName

# Unit tests only
docker exec -it pet php artisan test --testsuite=Unit

# Feature tests only
docker exec -it pet php artisan test --testsuite=Feature
```

---

## Checklist

- [ ] Every new public method in Actions, Services, Helpers has a test
- [ ] Pre-built mocks from `tests/Mocks/` are used where available
- [ ] `Queue::fake()` is used in tests that trigger Jobs
- [ ] No real external API calls in tests
- [ ] Feature tests use `RefreshDatabase`
- [ ] Test class is in the mirrored path under `tests/`
