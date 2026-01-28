# CLAUDE.md

Instructions for Claude Code when working with the TG Support Bot project.

## Project Description

TG Support Bot is a Laravel application for customer support via Telegram and VK. The project uses queues for asynchronous message processing and external API integrations.

## Tech Stack

- **PHP**: 8.2+
- **Framework**: Laravel 12
- **Database**: MySQL/PostgreSQL
- **Queues**: Laravel Queue
- **Containerization**: Docker
- **API Documentation**: L5-Swagger

## Project Structure

```
app/
├── Actions/          # Business logic commands
├── Contracts/        # Interfaces
├── DTOs/             # Data Transfer Objects
├── Enums/            # Enumerations
├── Helpers/          # Helper classes
├── Http/             # Controllers, Middleware, Requests
├── Jobs/             # Background tasks (queues)
├── Logging/          # Custom loggers
├── Models/           # Eloquent models
├── Providers/        # Service Providers
├── Services/         # Business logic services
├── TelegramBot/      # Telegram bot logic
└── VkBot/            # VK bot logic
```

## Development Commands

### Start the project
```bash
docker compose up -d
docker exec -it pet composer install
```

### Code formatting (Laravel Pint)
```bash
docker exec -it pet ./vendor/bin/pint
```

### Static analysis (PHPStan level 6)
```bash
docker exec -it pet ./vendor/bin/phpstan analyse
```

### Run tests
```bash
docker exec -it pet php artisan test
# or
docker exec -it pet ./vendor/bin/phpunit
```

### Run specific test
```bash
docker exec -it pet php artisan test --filter=TestName
```

## Code Standards

### Formatting (PSR-12 + Laravel)
- Indentation: 4 spaces
- Single quotes for strings
- Short array syntax `[]`
- Trailing comma in multiline arrays
- Remove unused imports
- Sort imports

### Naming Conventions
- Classes: `PascalCase`
- Methods and variables: `camelCase`
- Constants: `UPPER_SNAKE_CASE`
- Migration files: `snake_case`

### Architectural Rules
- **Controllers**: Thin, only handle HTTP requests/responses
- **Services**: Business logic
- **Models**: Only data operations, no business logic
- **Jobs**: Asynchronous tasks
- **DTOs**: For passing data between layers
- **Actions**: For isolated operations

### PHPDoc
- Required for public methods
- Format:
```php
/**
 * Brief method description.
 *
 * @param MessageDto $messageDto Parameter description
 * @return bool Return value description
 * @throws TelegramException When thrown
 */
```

## Commit Rules

### Message Format
```
issues-{number} | {brief description}
```

### Examples
```
issues-123 | add VK sticker support
issues-45 | fix telegram webhook error handling
issues-78 | update README with installation guide
```

### Change Types
- `add` — new feature
- `fix` — bug fix
- `update` — update existing functionality
- `refactor` — refactoring
- `remove` — removal
- `docs` — documentation
- `test` — tests
- `style` — formatting
- `chore` — routine tasks

## Branch Naming

```
issues-{number}
# or
issues-{number}-{brief-description}
```

Examples:
- `issues-38`
- `issues-45-fix-telegram-webhook`

## Testing

### Test Structure
```
tests/
├── Feature/    # HTTP endpoints, integrations
├── Unit/       # Services, helpers, DTOs
├── Mocks/      # Test mocks
├── Stubs/      # Data stubs
└── Traits/     # Reusable traits
```

### Tests for Services, Actions and Other Classes
- Example: class `app/Actions/External/DeleteMessage.php` must have a test located at `tests/Unit/Actions/External/DeleteMessageTest.php`
- Test classes must have the `*Test.php` suffix

### Requirements
- New functionality must be covered by tests
- Use `RefreshDatabase` trait for Feature tests
- Test naming: `test_can_do_something` or `test_throws_exception_when_invalid`

## Git Hooks

The project uses pre-commit and pre-push hooks:
- **pre-commit**: Laravel Pint (formatting)
- **pre-push**: PHPStan + PHPUnit

Scripts are located in `scripts/`:
- `scripts/pre-commit-check.sh`
- `scripts/pre-push-check.sh`

## Important Notes

1. **Never commit .env files** — they contain secrets
2. **Always run Pint before committing** — code must be formatted
3. **PHPStan level 6** — code must pass static analysis
4. **Tests must pass** — don't merge code with failing tests
5. **Use DTOs** for passing structured data
6. **Jobs for async operations** — sending messages, external APIs

## Post-Task Verification

1. Created methods have type hints for all arguments and return values
2. Created classes have tests in the `tests/` directory
3. New and edited files must pass checks described in `scripts/pre-commit-check.sh`
