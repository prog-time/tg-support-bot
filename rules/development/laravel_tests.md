# Laravel — Testing Rules & Guidelines

> **Version:** 1.0.0  
> **Last updated:** 2025-02-25  
> **Applies to:** Laravel 12.x / PHP 8.2+ / PHPUnit 11+  
> **Companion document:** `laravel-ai-agent-rules.md`

---

## 1. Overview

These rules define how tests must be written in Laravel projects. They apply to all test types: unit, feature, and integration. The goal is to produce a test suite that is fast, deterministic, readable, and trustworthy — a suite that the team and AI agents can rely on with confidence.

**Testing stack:**
- **PHPUnit 11+** — test runner
- **Laravel TestCase** — base class for feature tests
- **Pest PHP** — optional, allowed as an alternative syntax on top of PHPUnit
- **Mockery / PHPUnit mocks** — for test doubles
- **Laravel Factories** — for test data creation

---

## 2. Test Structure & File Organization

```
tests/
├── Unit/                        # Pure unit tests — no Laravel, no I/O
│   ├── Services/
│   │   └── UserServiceTest.php
│   ├── Domain/
│   │   └── MoneyTest.php
│   └── Actions/
│       └── CreateOrderActionTest.php
│
├── Feature/                     # Feature tests — full Laravel stack, real DB
│   ├── Api/
│   │   ├── UserControllerTest.php
│   │   └── OrderControllerTest.php
│   ├── Auth/
│   │   └── LoginTest.php
│   └── Jobs/
│       └── SendInvoiceJobTest.php
│
└── Support/                     # Shared test helpers, traits, base classes
    ├── Factories/               # Custom factory helpers
    └── Traits/
        └── ActsAsAdmin.php
```

**Rules:**
- Mirror the `app/` directory structure inside `tests/Unit/` and `tests/Feature/`
- One test class per production class
- Test class name = production class name + `Test` suffix: `UserService` → `UserServiceTest`
- File name must match the class name exactly

---

## 3. Test Types

### 3.1 Unit tests
Test a single class in complete isolation. No database, no HTTP, no filesystem, no Laravel container.

**Use for:**
- Services, Actions, domain objects, value objects
- DTO transformations and validation
- Helper functions and utilities
- Enum methods and business rules

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;   // ← NOT Tests\TestCase (no Laravel)
use PHPUnit\Framework\Attributes\Test;

final class UserServiceTest extends TestCase
{
    // ...
}
```

### 3.2 Feature tests
Test a full vertical slice through the Laravel application — HTTP request → middleware → controller → service → database → response. Always use the Laravel `TestCase`.

**Use for:**
- API endpoints and HTTP responses
- Authentication and authorization flows
- Form validation via FormRequest
- Events, listeners, notifications
- Artisan commands

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;                              // ← Laravel TestCase
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

final class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    // ...
}
```

### 3.3 Integration tests (when separate from Feature)
Test the interaction between your application code and an external system (database, cache, queue, mail). Lives in `tests/Feature/` unless the project has a dedicated `tests/Integration/` directory.

**Use for:**
- Repository implementations against a real DB
- Cache behaviour
- Queue dispatch and processing
- External service adapters

---

## 4. Naming Conventions

### 4.1 Test class names
```
{ProductionClassName}Test
```
Examples: `UserServiceTest`, `OrderControllerTest`, `CreateOrderActionTest`

### 4.2 Test method names
Use snake_case that reads as a plain-English sentence describing the scenario:

```
{method_or_scenario}_{condition}_{expected_outcome}
```

```php
// ✅ Good — reads like a specification
public function create_user_with_duplicate_email_throws_exception(): void
public function show_returns_404_when_user_does_not_exist(): void
public function admin_can_delete_any_user(): void
public function guest_cannot_access_protected_endpoint(): void

// ❌ Bad — vague, non-descriptive
public function test_user(): void
public function it_works(): void
public function testCreateUser(): void  // camelCase is allowed but discouraged
```

### 4.3 Test method attributes vs prefix
Prefer the `#[Test]` attribute over the `test_` prefix — it keeps method names cleaner:

```php
// ✅ Preferred
#[Test]
public function find_by_email_returns_null_when_not_found(): void {}

// ✅ Also acceptable
public function test_find_by_email_returns_null_when_not_found(): void {}
```

---

## 5. AAA Structure

Every test must follow the **Arrange → Act → Assert** pattern with explicit section comments.

```php
#[Test]
public function deactivate_user_sets_status_to_inactive(): void
{
    // Arrange
    $user = User::factory()->active()->create();

    // Act
    $this->actingAs($user)
        ->postJson("/api/users/{$user->id}/deactivate")
        ->assertOk();

    // Assert
    $this->assertDatabaseHas('users', [
        'id'     => $user->id,
        'status' => 'inactive',
    ]);
}
```

**Rules:**
- Always write all three `// Arrange`, `// Act`, `// Assert` comments — even for short tests
- Keep each section visually separated with a blank line
- One logical behaviour per test — if you need multiple `// Act` sections, split the test

---

## 6. Database & State Isolation

### 6.1 RefreshDatabase vs DatabaseTransactions

| Trait | When to use |
|---|---|
| `RefreshDatabase` | Default choice — migrates and truncates between tests |
| `DatabaseTransactions` | When tests don't modify schema and speed matters |
| Neither | Unit tests — never touch the database |

```php
// ✅ Most feature tests
use RefreshDatabase;

// ✅ Read-only integration tests where speed matters
use DatabaseTransactions;
```

### 6.2 Rules
- Never share database state between tests — each test is independent
- Never depend on test execution order
- Never use production seeders in tests — create only what the test needs via factories
- Reset any static state or singletons in `setUp()` if necessary

---

## 7. Factories

### 7.1 Always use factories to create test data
```php
// ✅ Correct
$user  = User::factory()->create();
$order = Order::factory()->for($user)->pending()->create();
$items = Product::factory()->count(3)->create();

// ❌ Incorrect — manual model creation bypasses factory states and is fragile
$user = new User(['name' => 'Test', 'email' => 'test@test.com']);
$user->save();
```

### 7.2 Define states for common scenarios
```php
// In UserFactory.php
public function admin(): static
{
    return $this->state(['role' => 'admin']);
}

public function unverified(): static
{
    return $this->state(['email_verified_at' => null]);
}

public function suspended(): static
{
    return $this->state(['status' => UserStatus::Suspended]);
}
```

```php
// In tests — expressive and readable
$admin     = User::factory()->admin()->create();
$suspended = User::factory()->suspended()->create();
$unverified = User::factory()->unverified()->create();
```

### 7.3 Use `make()` for unit tests that don't need persistence
```php
// ✅ make() — builds the model without saving to DB (faster in unit tests)
$user = User::factory()->make();

// ✅ create() — persists to DB (required for feature tests)
$user = User::factory()->create();
```

### 7.4 Provide only relevant overrides
```php
// ✅ Override only what matters for this test
$user = User::factory()->create(['email' => 'alice@example.com']);

// ❌ Over-specifying — noisy and brittle
$user = User::factory()->create([
    'name'              => 'Alice',
    'email'             => 'alice@example.com',
    'role'              => 'user',
    'email_verified_at' => now(),
    'created_at'        => now(),
]);
```

---

## 8. HTTP & API Testing

### 8.1 Always use JSON methods for API tests
```php
// ✅ Correct
$this->getJson('/api/users');
$this->postJson('/api/users', $payload);
$this->putJson("/api/users/{$id}", $payload);
$this->deleteJson("/api/users/{$id}");

// ❌ Avoid for JSON APIs
$this->get('/api/users');
$this->post('/api/users', $payload);
```

### 8.2 Assert status code first, then body
```php
$response = $this->actingAs($user)->postJson('/api/orders', $payload);

$response->assertCreated();                             // 201
$response->assertJsonStructure([
    'data' => ['id', 'status', 'total', 'created_at'],
]);
$response->assertJsonFragment(['status' => 'pending']);
```

### 8.3 Common status assertions

| Method | Status |
|---|---|
| `assertOk()` | 200 |
| `assertCreated()` | 201 |
| `assertNoContent()` | 204 |
| `assertBadRequest()` | 400 |
| `assertUnauthorized()` | 401 |
| `assertForbidden()` | 403 |
| `assertNotFound()` | 404 |
| `assertUnprocessable()` | 422 |

### 8.4 Test authentication states explicitly
```php
// ✅ Test as a specific user
$this->actingAs($user)->getJson('/api/profile');

// ✅ Test unauthenticated access
$this->getJson('/api/profile')->assertUnauthorized();

// ✅ Test role-based access
$this->actingAs($admin)->deleteJson("/api/users/{$target->id}")->assertNoContent();
$this->actingAs($regularUser)->deleteJson("/api/users/{$target->id}")->assertForbidden();
```

### 8.5 Validation testing
```php
#[Test]
#[DataProvider('provideInvalidRegistrationPayloads')]
public function registration_fails_with_invalid_payload(array $payload, string $field): void
{
    $this->postJson('/api/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
}

public static function provideInvalidRegistrationPayloads(): array
{
    return [
        'missing email'      => [['name' => 'Alice', 'password' => 'secret'], 'email'],
        'invalid email'      => [['name' => 'Alice', 'email' => 'not-an-email', 'password' => 'secret'], 'email'],
        'missing password'   => [['name' => 'Alice', 'email' => 'a@b.com'], 'password'],
        'password too short' => [['name' => 'Alice', 'email' => 'a@b.com', 'password' => '123'], 'password'],
    ];
}
```

---

## 9. Mocking & Test Doubles

### 9.1 Mock only external dependencies and I/O
```php
// ✅ Mock external services, mailers, queues
Mail::fake();
Queue::fake();
Storage::fake('s3');
Http::fake(['https://api.stripe.com/*' => Http::response(['id' => 'ch_123'], 200)]);
Event::fake();
Notification::fake();

// ❌ Don't mock what you own — test it for real or use factories
$this->mock(UserRepository::class, ...); // avoid unless truly necessary
```

### 9.2 Use Laravel fakes for framework features
```php
#[Test]
public function registration_sends_verification_email(): void
{
    // Arrange
    Mail::fake();
    $payload = ['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'password'];

    // Act
    $this->postJson('/api/register', $payload)->assertCreated();

    // Assert
    Mail::assertSent(EmailVerificationMail::class, function ($mail) {
        return $mail->hasTo('alice@example.com');
    });
}
```

### 9.3 Assert fakes after the act, not before
```php
// ✅ Correct order
Queue::fake();
$this->postJson('/api/orders', $payload);
Queue::assertPushed(ProcessOrderJob::class);

// ❌ Wrong — asserting before the action
Queue::assertPushed(ProcessOrderJob::class); // will always fail
$this->postJson('/api/orders', $payload);
```

### 9.4 PHPUnit mocks for unit tests
```php
#[Test]
public function notify_user_sends_notification_on_success(): void
{
    // Arrange
    $notifier = $this->createMock(NotifierInterface::class);
    $notifier->expects($this->once())
        ->method('send')
        ->with($this->isInstanceOf(WelcomeNotification::class));

    $service = new UserService($notifier);

    // Act
    $service->register(UserFactory::dto());
}
```

---

## 10. Testing Jobs, Events & Queues

### 10.1 Jobs
```php
#[Test]
public function placing_order_dispatches_process_order_job(): void
{
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/orders', ['product_id' => 1, 'quantity' => 2])
        ->assertCreated();

    Queue::assertPushed(ProcessOrderJob::class, function ($job) {
        return $job->order->product_id === 1;
    });
}

#[Test]
public function process_order_job_marks_order_as_completed(): void
{
    $order = Order::factory()->pending()->create();

    (new ProcessOrderJob($order))->handle();

    $this->assertDatabaseHas('orders', [
        'id'     => $order->id,
        'status' => 'completed',
    ]);
}
```

### 10.2 Events and Listeners
```php
#[Test]
public function user_registration_fires_user_registered_event(): void
{
    Event::fake([UserRegistered::class]);

    User::factory()->create();

    Event::assertDispatched(UserRegistered::class);
}
```

### 10.3 Notifications
```php
#[Test]
public function password_reset_sends_notification_to_user(): void
{
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/password/forgot', ['email' => $user->email])
        ->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
}
```

---

## 11. Testing Authorization (Policies & Gates)

```php
#[Test]
public function user_cannot_update_another_users_profile(): void
{
    $owner  = User::factory()->create();
    $other  = User::factory()->create();

    $this->actingAs($other)
        ->putJson("/api/users/{$owner->id}", ['name' => 'Hacked'])
        ->assertForbidden();
}

#[Test]
public function admin_can_update_any_profile(): void
{
    $admin  = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->putJson("/api/users/{$target->id}", ['name' => 'Updated'])
        ->assertOk();
}
```

---

## 12. Testing Artisan Commands

```php
#[Test]
public function sync_orders_command_processes_all_pending_orders(): void
{
    // Arrange
    Order::factory()->pending()->count(3)->create();
    Order::factory()->completed()->count(2)->create();

    // Act
    $this->artisan('app:sync-orders')
        ->assertSuccessful()
        ->expectsOutput('Processed 3 orders.');

    // Assert
    $this->assertDatabaseCount('orders', 5);
    $this->assertDatabaseMissing('orders', ['status' => 'pending']);
}
```

---

## 13. Database Assertions

```php
// Record exists with specific values
$this->assertDatabaseHas('users', [
    'email'  => 'alice@example.com',
    'status' => 'active',
]);

// Record does not exist
$this->assertDatabaseMissing('users', [
    'email' => 'deleted@example.com',
]);

// Exact count of records
$this->assertDatabaseCount('orders', 3);

// Soft-deleted record exists
$this->assertSoftDeleted('users', ['id' => $user->id]);

// Model was deleted
$this->assertModelMissing($user);

// Model still exists
$this->assertModelExists($user);
```

---

## 14. Data Providers

Use `#[DataProvider]` for testing the same behaviour across multiple inputs:

```php
#[Test]
#[DataProvider('provideOrderStatuses')]
public function only_pending_orders_can_be_cancelled(string $status, bool $canCancel): void
{
    $order = Order::factory()->create(['status' => $status]);

    if (! $canCancel) {
        $this->expectException(InvalidOrderStateException::class);
    }

    $order->cancel();

    if ($canCancel) {
        $this->assertSame('cancelled', $order->fresh()->status);
    }
}

public static function provideOrderStatuses(): array
{
    return [
        'pending order can be cancelled'   => ['pending',   true],
        'completed order cannot'           => ['completed', false],
        'cancelled order cannot'           => ['cancelled', false],
        'processing order cannot'          => ['processing', false],
    ];
}
```

---

## 15. Coverage & CI Requirements

### 15.1 Coverage thresholds
| Layer | Minimum coverage |
|---|---|
| Domain / Services / Actions | **90%** |
| HTTP Controllers | **80%** |
| Jobs / Listeners / Notifications | **80%** |
| Repositories | **80%** |
| Overall project | **80%** |

### 15.2 Running tests
```bash
# Run all tests
php artisan test

# Run in parallel (faster)
php artisan test --parallel

# Run with coverage report
php artisan test --coverage --min=80

# Run only unit tests
php artisan test --testsuite=Unit

# Run only feature tests
php artisan test --testsuite=Feature

# Run a specific test file
php artisan test tests/Feature/Api/UserControllerTest.php

# Run a specific test method
php artisan test --filter="show_returns_404_when_user_does_not_exist"
```

### 15.3 CI requirements
- All tests must pass before a PR can be merged
- Coverage must not drop below the defined thresholds
- Static analysis (PHPStan / Larastan level 8+) must pass
- No skipped tests without a linked issue in the skip reason

### 15.4 Skipping tests
```php
// ✅ Acceptable — with a reason and issue reference
#[Test]
#[RequiresPhpExtension('redis')]
public function caches_result_in_redis(): void {}

// ✅ Conditional skip
$this->markTestSkipped('Skipped until PROJ-456 is resolved.');

// ❌ Never — silent skip with no explanation
$this->markTestSkipped();
```

---

## 16. Forbidden Practices

- ❌ `sleep()` or `Carbon::setTestNow()` without resetting in `tearDown()`
- ❌ `dump()`, `dd()`, `var_dump()` left in any test
- ❌ Tests that depend on execution order
- ❌ Hardcoded IDs like `User::find(1)` — always use factory-created models
- ❌ Asserting on raw database queries inside unit tests
- ❌ Using production `.env` values or real external services in tests
- ❌ Tests with no assertions — a test that always passes proves nothing
- ❌ Commenting out a failing test instead of fixing it
- ❌ One test method testing multiple unrelated behaviours

---

## AI Agent Checklist

Before writing or modifying tests, verify that:

- [ ] Test class extends the correct base (`PHPUnit\TestCase` for unit, `Tests\TestCase` for feature)
- [ ] `RefreshDatabase` or `DatabaseTransactions` is used in all feature tests
- [ ] Test method name clearly describes the scenario and expected outcome
- [ ] Every test has `// Arrange`, `// Act`, `// Assert` comments
- [ ] Test data is created via factories only — no manual model instantiation
- [ ] Only relevant factory attributes are overridden
- [ ] Laravel fakes (`Mail::fake()`, `Queue::fake()`, etc.) are used instead of mocking framework internals
- [ ] Fakes are asserted **after** the action, not before
- [ ] Both the happy path and failure cases are covered
- [ ] Authentication state is explicitly set (`actingAs()` or unauthenticated)
- [ ] `#[DataProvider]` is used for repeated scenarios with different inputs
- [ ] No `sleep()`, `dd()`, or hardcoded IDs left in test code
- [ ] Coverage thresholds are met for the modified layer
