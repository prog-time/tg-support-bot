# PHP — AI Agent Rules & Guidelines

> **Template version:** 1.0.0  
> **Last updated:** 2025-02-25  
> **Applies to:** PHP 8.2+

---

## 1. Technology Overview

**PHP** is a general-purpose server-side scripting language designed primarily for web development. It runs on a wide range of platforms and powers everything from small scripts to large-scale enterprise systems. Modern PHP (8.0+) supports strong typing, enums, fibers, first-class callable syntax, and readonly properties — making it a capable language for writing clean, maintainable production code.

**Key characteristics:**
- **Paradigm:** OOP, Procedural, Functional (mixed)
- **Runtime:** PHP-FPM, CLI, Roadrunner, Swoole
- **Package manager:** Composer
- **Primary use cases:** Web APIs, CLI tools, Background jobs, CMS, Enterprise applications

---

## 2. Project Structure

```
php-project/
├── src/
│   ├── Domain/            # Domain models, value objects, domain exceptions
│   ├── Application/       # Use cases, services, DTOs, interfaces
│   ├── Infrastructure/    # Database, external APIs, filesystem, email
│   └── Presentation/      # Controllers, CLI commands, HTTP middleware
├── tests/
│   ├── Unit/              # Unit tests (no I/O, no framework)
│   └── Integration/       # Integration tests (DB, HTTP, external services)
├── config/                # Configuration files
├── public/                # Web entry point (index.php)
├── bin/                   # CLI entry points
├── var/
│   ├── cache/             # Generated cache files
│   └── log/               # Application logs
├── composer.json          # Dependency manifest
├── composer.lock          # Locked dependency versions
├── phpunit.xml            # PHPUnit configuration
└── phpstan.neon           # PHPStan configuration
```

**File organization rules:**
- One class, interface, trait, or enum per file
- The filename must exactly match the class name (e.g. `UserRepository.php`)
- Namespace mirrors the directory structure following PSR-4 autoloading
- Group code by domain/feature, not by technical layer, as the project grows
- Keep `public/` minimal — only the entry point and publicly served assets belong there

---

## 3. Code Writing Rules

### 3.1 General principles
- Follow SOLID principles throughout — especially Single Responsibility and Dependency Inversion
- Prefer composition over inheritance
- Use constructor Dependency Injection — never resolve dependencies manually with `new` inside classes
- Keep functions and methods focused and short (no more than 20–25 lines)
- Prefer immutability — use `readonly` classes and properties (PHP 8.2+) for value objects and DTOs
- Avoid global state, global functions, and `static` methods except for Named Constructors or factory methods
- Write code for the reader, not the machine — clarity beats cleverness

### 3.2 Naming conventions

| Entity | Style | Example |
|---|---|---|
| Classes, interfaces, traits, enums | PascalCase | `UserRepository`, `PaymentGatewayInterface` |
| Methods and functions | camelCase | `getUserById()`, `calculateTotal()` |
| Variables and parameters | camelCase | `$userId`, `$orderTotal` |
| Properties | camelCase | `$this->firstName` |
| Constants | UPPER_SNAKE_CASE | `MAX_RETRY_ATTEMPTS` |
| Enum cases | PascalCase | `Status::Active`, `Currency::Usd` |
| Interfaces | PascalCase + `Interface` suffix | `UserRepositoryInterface` |
| Abstract classes | PascalCase + `Abstract` prefix | `AbstractHandler` |
| Traits | PascalCase + `Trait` suffix | `HasTimestampsTrait` |
| Test classes | PascalCase + `Test` suffix | `UserServiceTest` |

### 3.3 Forbidden practices
- ❌ `eval()` — never, under any circumstances
- ❌ `extract()`, `compact()` — they obscure variable origins and break static analysis
- ❌ Error suppression operator `@` — handle errors explicitly
- ❌ `global` variables inside functions or methods
- ❌ Mixing business logic with I/O (database calls, HTTP requests) in the same class
- ❌ Catching `\Exception` or `\Throwable` without re-throwing or logging with full context
- ❌ Returning `null` to signal an error — throw an exception instead
- ❌ Magic numbers and strings — use named constants or enums
- ❌ Deep nesting (more than 2–3 levels) — extract methods or use early returns

### 3.4 Recommended patterns
- ✅ **Value Objects** — immutable objects representing domain concepts (Money, Email, UserId)
- ✅ **Repository Pattern** — abstract all data access behind an interface
- ✅ **Service / Use Case classes** — one class per business operation
- ✅ **DTO (Data Transfer Objects)** — typed, readonly structs for moving data between layers
- ✅ **Named Constructors** — `User::fromArray()`, `Money::ofAmount()` instead of complex constructors
- ✅ **Result / Either pattern** — for operations that can fail without exceptions being the right tool
- ✅ **Enums (PHP 8.1+)** — replace string/int constants for statuses, types, and categories

### 3.5 Dependency management
- Declare dependencies in the constructor — never in methods
- Depend on interfaces, not concrete implementations
- Use a DI container for wiring — never instantiate services by hand in application code
- Keep `composer.json` clean — remove unused packages promptly
- Pin dependencies with `composer.lock` — always commit the lockfile

---

## 4. Notations & Annotations

### 4.1 Class documentation
```php
<?php

declare(strict_types=1);

namespace App\Domain\User;

/**
 * Represents a registered user in the system.
 *
 * This is an aggregate root — all user mutations go through this class.
 */
final class User
{
    public function __construct(
        private readonly UserId $id,
        private string $email,
        private UserStatus $status,
    ) {}
}
```

### 4.2 Interface documentation
```php
/**
 * Defines the contract for reading and persisting User aggregates.
 */
interface UserRepositoryInterface
{
    /**
     * @throws UserNotFoundException
     */
    public function findById(UserId $id): User;

    public function save(User $user): void;
}
```

### 4.3 Method documentation
```php
/**
 * Calculate the total price including applicable tax.
 *
 * @param  Money    $price    Net price before tax
 * @param  float    $taxRate  Tax rate as a decimal (e.g. 0.19 for 19%)
 * @return Money              Gross price including tax
 *
 * @throws \InvalidArgumentException If tax rate is negative or greater than 1
 */
public function calculateGross(Money $price, float $taxRate): Money
```

### 4.4 PHP 8.x native attributes
```php
// Marking a method as a test (PHPUnit)
#[Test]
#[DataProvider('provideValidEmails')]
public function validates_correct_email_format(string $email): void {}

// Readonly value object (PHP 8.2)
readonly class Money
{
    public function __construct(
        public int $amount,
        public Currency $currency,
    ) {}
}

// Backed Enum with behaviour
enum Status: string
{
    case Active   = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match($this) {
            Status::Active   => 'Active',
            Status::Inactive => 'Inactive',
        };
    }
}
```

### 4.5 Comment writing rules
- Comment **why**, not **what** — the code explains what; comments explain intent and context
- `TODO` always includes author and ticket: `// TODO(alice): remove once PROJ-123 is deployed`
- `FIXME` for known bugs that cannot be fixed immediately: `// FIXME(bob): race condition under high load`
- Never leave commented-out dead code in the main branch — use version control instead
- Avoid obvious comments that restate the code: `// increment counter` above `$count++` adds no value

---

## 5. Type System

### 5.1 General requirements
- Every PHP file must begin with `declare(strict_types=1)`
- All function/method parameters and return types must be explicitly declared
- Never use `mixed` without a comment explaining why it cannot be avoided
- Avoid `array` as a type hint — use typed arrays via PHPDoc (`array<int, User>`) or typed collections
- Use `?Type` (nullable shorthand) over `Type|null` for readability

### 5.2 Correct typing examples
```php
// ✅ Correct — explicit types everywhere
public function findActiveUsers(int $limit): array
{
    // ...
}

public function findByEmail(string $email): ?User
{
    // returns null if not found — intentional and documented
}

// ❌ Incorrect — missing types
public function findUser($id) // what type is $id? what does it return?
{
    // ...
}
```

### 5.3 Nullable and optional values
```php
// Nullable return — explicitly communicates "not found" is a valid outcome
public function findByToken(string $token): ?User
{
    // ...
}

// Optional parameter with a typed default
public function paginate(int $page = 1, int $perPage = 15): array
{
    // ...
}

// Nullable parameter — use only when null carries distinct meaning
public function filterByStatus(?Status $status = null): array
{
    // null means "no filter", not "unknown"
}
```

### 5.4 Generics and typed collections (via PHPDoc)
```php
/**
 * @param  array<string, mixed>  $data
 * @return list<User>
 */
public function hydrateMany(array $data): array {}

/**
 * @return \ArrayObject<int, Order>
 */
public function getPendingOrders(): \ArrayObject {}
```

### 5.5 Union types and intersection types (PHP 8.0+)
```php
// Union type
public function render(string|Stringable $content): string {}

// Intersection type (PHP 8.1) — must implement both interfaces
public function process(Countable&Iterator $collection): void {}

// Never return type — function always throws or exits
public function fail(string $message): never
{
    throw new \RuntimeException($message);
}
```

---

## 6. Error Handling & Exceptions

### 6.1 Exception hierarchy
```php
// Base application exception — catch-all for domain-level errors
class AppException extends \RuntimeException {}

// Domain-specific exceptions — express intent clearly
class UserNotFoundException extends AppException
{
    public static function withId(int $id): self
    {
        return new self("User with ID {$id} was not found.");
    }
}

class DuplicateEmailException extends AppException
{
    public static function forEmail(string $email): self
    {
        return new self("A user with email '{$email}' already exists.");
    }
}

// Infrastructure exceptions — separate from domain
class DatabaseConnectionException extends \RuntimeException {}
```

### 6.2 Error handling rules
- Never catch an exception just to silently ignore it — at minimum, log it
- Catch the most specific exception type possible
- Do not use exceptions for normal control flow (e.g. checking if a record exists)
- Re-throw infrastructure exceptions as domain exceptions at the boundary when appropriate
- Always include context when logging exceptions — message alone is not enough

### 6.3 Logging with context
```php
// ✅ Correct — structured log with context
$this->logger->error('Failed to charge customer', [
    'user_id'   => $userId,
    'amount'    => $amount,
    'currency'  => $currency->value,
    'error'     => $e->getMessage(),
    'trace'     => $e->getTraceAsString(),
]);

// ❌ Incorrect — context-free log
$this->logger->error($e->getMessage());
```

### 6.4 Error levels guidance
| Situation | Level |
|---|---|
| Expected domain failure (not found, validation) | `warning` |
| Unexpected application error | `error` |
| System is unusable / data corruption risk | `critical` |
| Debugging information (dev only) | `debug` |

---

## 7. Testing

### 7.1 Unit tests

**What to cover:**
- Domain models, value objects, and business rules
- Service / use case methods with mocked dependencies
- Edge cases, boundary values, and invalid inputs
- Enum methods and pure helper functions

**Conventions:**
- Test method name: `methodName_condition_expectedOutcome` (snake_case)
- Structure every test with **AAA**: Arrange → Act → Assert
- One logical assertion per test — split scenarios into separate methods
- Use `#[Test]` attribute (PHPUnit 11+) or `test_` prefix
- Never use `@dataProvider` with hardcoded strings — always use `#[DataProvider]` attribute

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\User\User;
use App\Domain\User\UserStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    #[Test]
    public function deactivate_changes_status_to_inactive(): void
    {
        // Arrange
        $user = User::create(id: 1, email: 'alice@example.com');

        // Act
        $user->deactivate();

        // Assert
        $this->assertSame(UserStatus::Inactive, $user->status());
    }

    #[Test]
    public function deactivate_throws_when_user_is_already_inactive(): void
    {
        $user = User::create(id: 1, email: 'alice@example.com');
        $user->deactivate();

        $this->expectException(\DomainException::class);

        $user->deactivate();
    }
}
```

### 7.2 Integration tests

**What to cover:**
- Repository implementations against a real database
- External service adapters (HTTP clients, queues, storage)
- Full request/response cycles for HTTP handlers
- Database transaction behaviour and constraint enforcement

**Conventions:**
- Wrap each test in a database transaction and roll back after — use a `DatabaseTestCase` base class
- Never share state between tests — reset all fixtures per test
- Use dedicated test fixtures or factories — never rely on production seed data
- Run integration tests in a separate suite from unit tests in CI

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure;

use App\Domain\User\UserId;
use App\Domain\User\UserNotFoundException;
use App\Infrastructure\Persistence\DoctrineUserRepository;
use Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Test;

final class DoctrineUserRepositoryTest extends DatabaseTestCase
{
    private DoctrineUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DoctrineUserRepository($this->entityManager);
    }

    #[Test]
    public function find_by_id_throws_when_user_does_not_exist(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->repository->findById(UserId::fromInt(9999));
    }

    #[Test]
    public function save_persists_and_retrieves_user_correctly(): void
    {
        // Arrange
        $user = UserFixture::active();

        // Act
        $this->repository->save($user);
        $found = $this->repository->findById($user->id());

        // Assert
        $this->assertEquals($user->id(), $found->id());
        $this->assertEquals($user->email(), $found->email());
    }
}
```

### 7.3 Data providers
```php
#[Test]
#[DataProvider('provideInvalidEmails')]
public function rejects_malformed_email_addresses(string $email): void
{
    $this->expectException(\InvalidArgumentException::class);
    Email::from($email);
}

public static function provideInvalidEmails(): array
{
    return [
        'missing @ sign'   => ['notanemail'],
        'missing domain'   => ['user@'],
        'empty string'     => [''],
        'whitespace only'  => ['   '],
    ];
}
```

### 7.4 Coverage and CI
- Minimum coverage threshold: **80%** for domain and application layers
- Unit and integration test suites run in parallel in CI on every PR
- Static analysis (PHPStan level 8+) runs as a separate CI step
- Use mutation testing (Infection) for critical business logic modules

---

## 8. Security

- Never trust user input — validate and sanitize everything at the application boundary
- Use prepared statements or a query builder for all database interactions — never concatenate SQL
- Hash passwords exclusively with `password_hash()` using `PASSWORD_BCRYPT` or `PASSWORD_ARGON2ID` — never MD5 or SHA1
- Store secrets in environment variables — never hardcode credentials, API keys, or tokens in source code
- Avoid `unserialize()` on untrusted data — use JSON instead
- Escape all output to prevent XSS — use context-aware escaping (HTML, JS, URL)
- Apply the principle of least privilege for database users, filesystem permissions, and API keys
- Validate and whitelist file uploads — check MIME type, extension, and size server-side
- Use `random_bytes()` or `random_int()` for all security-sensitive random values — never `rand()` or `mt_rand()`
- Set appropriate HTTP security headers: `Content-Security-Policy`, `X-Content-Type-Options`, `Strict-Transport-Security`

---

## 9. Performance

- **Profile before optimizing** — never guess; use Xdebug, Blackfire, or Tideways to find real bottlenecks
- **OpCache** — always enabled in production (`opcache.enable=1`, `opcache.validate_timestamps=0`)
- **Autoloading** — run `composer dump-autoload --optimize --classmap-authoritative` in production
- **Avoid loading all records** — paginate or use generators / cursors for large datasets
- **Use generators** for memory-efficient iteration over large collections:
  ```php
  function readLines(string $file): \Generator
  {
      $handle = fopen($file, 'r');
      while (($line = fgets($handle)) !== false) {
          yield trim($line);
      }
      fclose($handle);
  }
  ```
- **Cache expensive computations** — use PSR-6 / PSR-16 compatible cache adapters
- **Avoid repeated I/O in loops** — batch database queries, bulk API calls
- **Use typed properties and readonly** — reduces runtime overhead from dynamic property lookups

---

## 10. Environment Variables & Configuration

```dotenv
# Application
APP_ENV=production          # development | testing | production
APP_DEBUG=false             # Never true in production
APP_SECRET=                 # Cryptographic secret — generate with: php -r "echo bin2hex(random_bytes(32));"

# Database
DB_DRIVER=pdo_mysql         # pdo_mysql | pdo_pgsql | pdo_sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=app
DB_USER=app_user
DB_PASSWORD=

# Cache
CACHE_DSN=redis://localhost # redis:// | memcached:// | file://var/cache

# Mailer
MAILER_DSN=smtp://localhost:1025

# Logging
LOG_LEVEL=warning           # debug | info | notice | warning | error | critical
LOG_CHANNEL=stderr          # stderr | file | syslog
```

**Rules:**
- All secrets and environment-specific values must come from environment variables — never from committed files
- Provide a `.env.example` file documenting every variable with a safe placeholder — keep it up to date
- Never call `getenv()` directly in business code — load all config once at bootstrap and inject it
- Use separate `.env.test` for the test environment to isolate test credentials

---

## 11. Additional Tools & Integrations

| Tool | Purpose | Version |
|---|---|---|
| Composer | Dependency management | ^2.7 |
| PHPUnit | Testing framework | ^11.0 |
| PHPStan | Static analysis | ^2.0 |
| PHP CS Fixer | Code formatting (PSR-12) | ^3.0 |
| Infection | Mutation testing | ^0.29 |
| Psalm | Alternative static analysis | ^5.0 |
| Xdebug | Debugging and profiling | ^3.3 |
| Blackfire | Production profiling | latest |

---

## 12. References & Documentation

### Official documentation
- 📘 [PHP Manual](https://www.php.net/manual/en/)
- 📗 [PHP Function Reference](https://www.php.net/manual/en/funcref.php)
- 📙 [PHP Changelog / Migration Guides](https://www.php.net/ChangeLog-8.php)
- 🔄 [PHP 8.2 → 8.3 Migration](https://www.php.net/manual/en/migration83.php)

### Standards
- 📐 [PSR-1: Basic Coding Standard](https://www.php-fig.org/psr/psr-1/)
- 📐 [PSR-4: Autoloading Standard](https://www.php-fig.org/psr/psr-4/)
- 📐 [PSR-12: Extended Coding Style](https://www.php-fig.org/psr/psr-12/)
- 📐 [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/)
- 📐 [PSR-7: HTTP Message Interface](https://www.php-fig.org/psr/psr-7/)

### Learning resources
- 🎓 [PHP The Right Way](https://phptherightway.com/) — community best practices guide
- 🎓 [Laracasts — PHP for Beginners](https://laracasts.com/series/php-for-beginners) — practical video series
- 🎓 [DesignPatternsPHP](https://designpatternsphp.readthedocs.io/) — PHP pattern examples

### Tools documentation
- 🛠️ [PHPStan Docs](https://phpstan.org/user-guide/getting-started)
- 🛠️ [PHP CS Fixer Docs](https://cs.symfony.com/)
- 🛠️ [PHPUnit Docs](https://docs.phpunit.de/en/11.0/)
- 🛠️ [Infection Mutation Testing](https://infection.github.io/)

### Internal resources
- 🏠 [ADR (Architecture Decision Records)](./docs/adr/)
- 🏠 [CHANGELOG.md](./CHANGELOG.md)

---

## AI Agent Checklist

Before generating or modifying any PHP code, verify that:

- [ ] File starts with `declare(strict_types=1)`
- [ ] Namespace and file path follow PSR-4 conventions
- [ ] All parameters and return types are explicitly declared
- [ ] No use of `mixed`, `array` (untyped), or missing return types
- [ ] No `eval()`, `extract()`, `compact()`, or `@` error suppression
- [ ] No global state or `global` keyword
- [ ] Dependencies are injected via the constructor, not instantiated inline
- [ ] All classes depend on interfaces, not concrete implementations
- [ ] Exceptions are domain-specific and extend the base `AppException`
- [ ] No empty `catch` blocks — exceptions are logged with full context
- [ ] Unit tests written for all new domain and service logic
- [ ] Integration tests written for all I/O boundaries (DB, HTTP, filesystem)
- [ ] No magic numbers or hardcoded strings — use constants or enums
- [ ] No secrets or credentials in source code
- [ ] PHPDoc added for generics, complex arrays, and non-obvious return types
