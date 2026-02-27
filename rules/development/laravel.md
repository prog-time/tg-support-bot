# Laravel — AI Agent Rules & Guidelines

> **Template version:** 1.0.0  
> **Last updated:** 2025-02-25  
> **Applies to:** Laravel 12.x / PHP 8.2+

---

## 1. Technology Overview

**Laravel** is a PHP web application framework built on the MVC (Model-View-Controller) pattern, focused on expressive syntax and developer experience. It is used to build REST APIs, full-stack web applications, background job processors, and CLI tools.

**Key characteristics:**
- **Paradigm:** MVC, Service Layer, Repository Pattern
- **Language:** PHP 8.2+
- **Ecosystem:** Composer, Artisan CLI, Eloquent ORM, Blade, Vite
- **Primary use cases:** Web API, Full-stack Web, Background Jobs

---

## 2. Project Structure

```
laravel-app/
├── app/
│   ├── Console/           # Custom Artisan commands
│   ├── Exceptions/        # Custom exceptions and exception handler
│   ├── Http/
│   │   ├── Controllers/   # Controllers — request orchestration only
│   │   ├── Middleware/    # HTTP middleware
│   │   └── Requests/      # Form Request classes — validation & authorization
│   ├── Models/            # Eloquent models
│   ├── Providers/         # Service Providers
│   ├── Services/          # Business logic
│   ├── Repositories/      # Data access layer (optional)
│   ├── DTO/               # Data Transfer Objects
│   ├── Actions/           # Single-action classes (optional)
│   └── Enums/             # PHP Enums (statuses, types)
├── bootstrap/
│   └── app.php            # Middleware, routes, exceptions registration (Laravel 11+)
├── config/                # Configuration files
├── database/
│   ├── factories/         # Model factories for tests
│   ├── migrations/        # Database migrations
│   └── seeders/           # Database seeders
├── resources/
│   ├── views/             # Blade templates
│   └── lang/              # Localization files
├── routes/
│   ├── web.php            # Web routes
│   ├── api.php            # API routes
│   └── console.php        # Artisan routes
├── storage/               # Logs, cache, uploaded files
└── tests/
    ├── Unit/              # Unit tests
    └── Feature/           # Feature / integration tests
```

**File organization rules:**
- One class per file; the filename must match the class name exactly
- Group by domain inside `app/` as the project grows: `app/Domains/User/`, `app/Domains/Order/`
- Controllers must not contain business logic — only call a service and return a response
- Business logic lives in `Services/` or `Actions/`, never in models or controllers

---

## 3. Code Writing Rules

### 3.1 General principles
- Follow SOLID principles, especially Single Responsibility and Dependency Inversion
- Use constructor Dependency Injection — never instantiate dependencies with `new` inside classes
- Keep methods small and focused (no more than 20–25 lines)
- Use `readonly` properties and classes (PHP 8.2+) for immutable DTOs
- Avoid facades inside services — use them only in controllers and route files
- Avoid static methods unless clearly justified (e.g. Named Constructors)

### 3.2 Naming conventions

| Entity | Style | Example |
|---|---|---|
| Classes, interfaces, traits | PascalCase | `UserService`, `PaymentInterface` |
| Methods | camelCase | `getUserById()`, `markAsPaid()` |
| Variables | camelCase | `$userId`, `$orderTotal` |
| Model properties | snake_case | `$user->first_name` |
| Constants | UPPER_SNAKE_CASE | `MAX_LOGIN_ATTEMPTS` |
| Enum cases | PascalCase | `Status::Active`, `Role::Admin` |
| Database tables | snake_case, plural | `user_profiles`, `order_items` |
| Migrations | snake_case with date | `2024_01_01_create_users_table` |
| Artisan commands | kebab-case | `app:sync-orders` |
| Routes | kebab-case | `/user-profiles`, `/order-items` |
| Blade templates | snake_case or kebab | `user_profile.blade.php` |

### 3.3 Forbidden practices
- ❌ Business logic in controllers — only service calls and responses
- ❌ Raw SQL queries via string concatenation — use Query Builder / Eloquent
- ❌ `DB::statement()` with concatenated user input
- ❌ Business rules inside migrations or seeders
- ❌ Calling `env()` directly outside `config/` files — always use `config('key')`
- ❌ Leaving `dd()`, `dump()`, or `var_dump()` in code
- ❌ Committing `.env` files to the repository
- ❌ `Model::all()` without constraints in production code

### 3.4 Recommended patterns
- ✅ **Service Layer** — `UserService` is called from the controller and holds business logic
- ✅ **Form Requests** — all validation via `php artisan make:request`
- ✅ **API Resources** — data transformation via `JsonResource` and `ResourceCollection`
- ✅ **Repository Pattern** — for complex queries and abstracting over Eloquent
- ✅ **Action classes** — for single operations: `CreateOrderAction::execute()`
- ✅ **Enums** — instead of string/integer constants for statuses and types
- ✅ **DTOs** — for passing data between layers instead of plain arrays

### 3.5 Working with Eloquent
- Define relationships explicitly with return types
- Use `with()` for eager loading — never trigger N+1 queries
- Use scope methods for reusable query conditions: `scopeActive()`, `scopeForUser()`
- Never access `$request` inside a model
- Use `$fillable` instead of `$guarded = []` for mass-assignment safety

---

## 4. Notations & Annotations

### 4.1 Class documentation
```php
<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Handles business logic for user management.
 *
 * @author [Author Name]
 */
final class UserService
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}
}
```

### 4.2 Method documentation
```php
/**
 * Find a user by their ID or throw an exception.
 *
 * @param  int  $id  The user's identifier
 * @return User      The found user
 *
 * @throws UserNotFoundException If no user exists with that ID
 */
public function findOrFail(int $id): User
{
    return $this->users->findOrFail($id);
}
```

### 4.3 PHP 8.x attributes in Laravel
```php
// Route attributes (Laravel 11+)
#[Get('/users/{id}')]
public function show(int $id): JsonResponse {}

// Readonly DTO
readonly class CreateUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}

// Backed Enum
enum UserStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Banned   = 'banned';
}
```

### 4.4 Comment writing rules
- Comment **why**, not **what** — the code itself should explain what it does
- `TODO` with author and context: `// TODO(john): remove after migration to new payment API`
- Never leave commented-out code in main branches
- For complex SQL or algorithms, briefly describe the intent above the block

---

## 5. Type System

### 5.1 General requirements
- Always add `declare(strict_types=1)` at the top of every PHP file
- All method arguments and return types must have explicit type declarations
- Never use `mixed` without a justifying comment
- Use union types instead of `mixed`: `int|string`, `User|null`
- Prefer `?Type` over `Type|null` for nullable types — it's shorter and more readable

### 5.2 Correct typing examples
```php
// ✅ Correct
public function createUser(CreateUserDTO $dto): User
{
    // ...
}

public function findByEmail(string $email): ?User
{
    return User::where('email', $email)->first();
}

public function getActiveIds(): array
{
    return User::active()->pluck('id')->toArray();
}

// ❌ Incorrect
public function createUser($data)       // missing types
{
    // ...
}

public function find($id): mixed        // unjustified use of mixed
{
    // ...
}
```

### 5.3 Nullable and optional values
```php
// Nullable return type
public function findByToken(string $token): ?User
{
    return User::where('token', $token)->first();
}

// Optional parameter with default value
public function paginate(?int $perPage = null): LengthAwarePaginator
{
    return User::paginate($perPage ?? 15);
}
```

### 5.4 Collections and generics (via PHPDoc)
```php
/**
 * @return Collection<int, User>
 */
public function getActiveUsers(): Collection
{
    return User::active()->get();
}

/**
 * @param  array<string, mixed>  $filters
 * @return LengthAwarePaginator<User>
 */
public function search(array $filters): LengthAwarePaginator
{
    // ...
}
```

---

## 6. Error Handling & Exceptions

### 6.1 Exception hierarchy
```php
// Base application exception
namespace App\Exceptions;

class AppException extends RuntimeException {}

// Domain-specific exceptions
class UserNotFoundException extends AppException
{
    public static function byId(int $id): self
    {
        return new self("User [{$id}] not found.");
    }
}

class InsufficientBalanceException extends AppException {}
```

### 6.2 Registering the exception handler (bootstrap/app.php — Laravel 11+)
```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (UserNotFoundException $e, Request $request) {
        return response()->json(['message' => $e->getMessage()], 404);
    });

    $exceptions->render(function (AppException $e, Request $request) {
        return response()->json(['message' => $e->getMessage()], 422);
    });
})
```

### 6.3 Error handling rules
- Never swallow exceptions with empty `catch` blocks
- Always log with context: use `Log::error()` with a data array
- Separate domain exceptions (`UserNotFoundException`) from infrastructure exceptions (`ConnectionException`)
- Do not use `try/catch` in controllers — rely on the global exception handler

### 6.4 Logging
```php
// ✅ Correct — log with context
Log::error('Payment failed', [
    'user_id'   => $userId,
    'amount'    => $amount,
    'gateway'   => $gateway,
    'exception' => $e->getMessage(),
]);

// ❌ Incorrect — log message only
Log::error($e->getMessage());
```

---

## 7. Testing

### 7.1 Unit tests

**What to cover:**
- Service and action class methods
- Data transformation (DTOs, Resources)
- Edge cases and invalid input
- Enum logic and helper functions

**Conventions:**
- Test name format: `method_condition_expectedResult` (snake_case)
- Structure: **AAA** (Arrange → Act → Assert)
- One assertion per test where possible
- Use the `#[Test]` attribute (PHP 8) or the `test_` prefix

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\UserService;
use App\Repositories\UserRepository;
use App\Exceptions\UserNotFoundException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class UserServiceTest extends TestCase
{
    #[Test]
    public function find_or_fail_throws_when_user_not_found(): void
    {
        // Arrange
        $repository = $this->createMock(UserRepository::class);
        $repository
            ->method('find')
            ->willReturn(null);

        $service = new UserService($repository);

        // Assert
        $this->expectException(UserNotFoundException::class);

        // Act
        $service->findOrFail(999);
    }
}
```

### 7.2 Feature / Integration tests

**What to cover:**
- HTTP endpoints (status codes, JSON response structure)
- Database changes after requests
- Authentication and authorization flows
- Queues, events, and notifications

**Conventions:**
- Extend `Tests\TestCase` (Laravel's TestCase)
- Use `RefreshDatabase` for test isolation
- Create test data via factories, not seeders
- Assert response shape with `assertJsonStructure()` and `assertJsonFragment()`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function show_returns_user_data_for_authenticated_user(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson("/api/users/{$user->id}");

        // Assert
        $response
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'email']])
            ->assertJsonFragment(['email' => 'john@example.com']);
    }

    #[Test]
    public function show_returns_404_for_nonexistent_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/users/999')
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'User [999] not found.']);
    }
}
```

### 7.3 Factories
```php
// ✅ Use states to model specific scenarios
User::factory()->admin()->create();
User::factory()->unverified()->count(5)->create();
Order::factory()->for($user)->paid()->create();
```

### 7.4 Coverage and CI
- Minimum coverage threshold: **80%** for services and domain logic
- Tests must run automatically in CI on every PR
- Use the `--parallel` flag to speed up the suite: `php artisan test --parallel`
- Use a dedicated test database or `:memory:` SQLite

---

## 8. Security

- Always use Form Requests for input validation — never pass `$request->all()` unfiltered
- Parameterized queries are mandatory — never concatenate SQL: `where('id', $id)`, not `whereRaw("id = $id")`
- Sensitive data (passwords, tokens, keys) — only in `.env`, accessed via `config()` in code
- Hash passwords with `Hash::make()` — never use MD5 or SHA1
- Use Laravel Sanctum or Passport for API authentication
- Use Policies and Gates for authorization — never hardcode roles in controllers
- CSRF protection is enabled by default for web routes — do not disable without reason
- Apply rate limiting on public and auth endpoints via `throttle:` middleware
- Always define `$fillable` on models — avoid `$guarded = []`

---

## 9. Performance

- **N+1 problem** — always use `with()` for eager loading relationships
- **Caching** — cache expensive queries with `Cache::remember()` and a sensible TTL
- **Pagination** — never use `->get()` on large datasets; use `->paginate()` or `->chunk()`
- **Queues** — push heavy operations (emails, PDF generation, external API calls) to a queue via `dispatch(new Job())`
- **Disable lazy loading** in `AppServiceProvider` to catch N+1 issues early in development:
  ```php
  Model::preventLazyLoading(! app()->isProduction());
  ```
- **Select only needed columns** — `->select(['id', 'name', 'email'])` instead of `SELECT *`
- **Database indexes** — add `->index()` in migrations for columns used in `where` / `orderBy`
- **Octane** — consider Laravel Octane (Swoole / RoadRunner) for high-throughput APIs

---

## 10. Environment Variables & Configuration

```dotenv
# Application
APP_NAME=Laravel
APP_ENV=local           # local | staging | production
APP_KEY=                # Generate with: php artisan key:generate
APP_DEBUG=true          # Must be false in production
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=

# Cache & Sessions
CACHE_DRIVER=redis      # file | redis | memcached
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis  # sync | database | redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS=noreply@example.com

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

**Rules:**
- All sensitive data must live in `.env` — never hardcode in source files
- Keep `.env.example` up to date — it serves as configuration documentation for the team
- Always access values via `config('app.key')`, never via `env('APP_KEY')` directly in code
- Use a separate `.env.testing` file for the test environment

---

## 11. Additional Tools & Integrations

| Tool | Purpose | Version |
|---|---|---|
| Laravel Pint | Code formatting (PSR-12) | ^1.0 |
| PHPStan / Larastan | Static analysis (level 8+) | ^2.0 |
| Laravel Telescope | Debugging and profiling (dev only) | ^5.0 |
| Laravel Horizon | Redis queue monitoring | ^5.0 |
| Laravel Sanctum | API token authentication | ^4.0 |
| Laravel Scout | Full-text search | ^10.0 |
| Spatie Permission | Roles and permissions | ^6.0 |
| PHPUnit | Testing | ^11.0 |

---

## 12. References & Documentation

### Official documentation
- 📘 [Laravel Documentation](https://laravel.com/docs)
- 📗 [Laravel API Reference](https://laravel.com/api/12.x/)
- 📙 [Release Notes](https://laravel.com/docs/12.x/releases)
- 🔄 [Upgrade Guide](https://laravel.com/docs/12.x/upgrade)

### Ecosystem
- 🛠️ [Eloquent ORM](https://laravel.com/docs/12.x/eloquent)
- 🛠️ [Laravel Sanctum](https://laravel.com/docs/12.x/sanctum)
- 🛠️ [Laravel Horizon](https://laravel.com/docs/12.x/horizon)
- 🛠️ [Laravel Pint](https://laravel.com/docs/12.x/pint)
- 🛠️ [Larastan (PHPStan for Laravel)](https://github.com/larastan/larastan)

### Code standards
- 📐 [PSR-12: Extended Coding Style](https://www.php-fig.org/psr/psr-12/)
- 📐 [PHP The Right Way](https://phptherightway.com/)

### Learning resources
- 🎓 [Laracasts](https://laracasts.com) — official video learning platform
- 🎓 [Laravel Daily](https://laraveldaily.com) — practical tips and best practices

### Internal resources
- 🏠 [ADR (Architecture Decision Records)](./docs/adr/)
- 🏠 [CHANGELOG.md](./CHANGELOG.md)

---

## AI Agent Checklist

Before generating or modifying any code, verify that:

- [ ] Every PHP file starts with `declare(strict_types=1)`
- [ ] All method arguments and return types are explicitly declared
- [ ] Controllers contain no business logic — only service calls and responses
- [ ] All input validation is handled by a `FormRequest`
- [ ] No N+1 queries — `with()` is used for all relationships
- [ ] No direct `env()` calls outside `config/` files
- [ ] Exceptions are custom classes that extend `AppException`
- [ ] All log calls include a context array (`Log::error('...', [...])`)
- [ ] Unit and feature tests are written or updated
- [ ] Secrets are in `.env` and `.env.example` is updated
- [ ] No `dd()`, `dump()`, or commented-out code left behind
- [ ] API responses are transformed via `JsonResource`
- [ ] `$fillable` is defined on every model
