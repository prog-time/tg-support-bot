# Code Style Rules

> **Version:** 1.0.0
> **Context:** Read this file before writing or reviewing any PHP code in this project. Also read `rules/development/php.md` and `rules/development/laravel.md` for general rules.

---

## 1. Mandatory file header

Every PHP file must start with `declare(strict_types=1)`.

```php
// ✅ Correct
<?php

declare(strict_types=1);

namespace App\Services\Tg;

// ❌ Incorrect — missing strict types
<?php

namespace App\Services\Tg;
```

---

## 2. Type declarations

All method parameters and return types must be explicitly declared. `mixed` is only acceptable with a PHPDoc comment explaining why.

```php
// ✅ Correct
public function execute(BotUser $botUser): void
public function fromRequest(Request $request): ?self
public function getKeyboard(BotUser $botUser): array

// ❌ Incorrect
public function execute($botUser)
public function getKeyboard($botUser): mixed
```

---

## 3. PHPDoc requirements

Required for all **public** methods. Must include `@param`, `@return`, and `@throws` where applicable.

```php
// ✅ Correct
/**
 * Send contact information message to the support group.
 *
 * @param BotUser $botUser The user whose contact info to send
 * @return void
 * @throws \Exception When Telegram API call fails
 */
public function execute(BotUser $botUser): void

// ❌ Incorrect — no PHPDoc on public method
public function execute(BotUser $botUser): void
{
    // ...
}
```

---

## 4. DTO rules

All DTOs must extend `Spatie\LaravelData\Data`.

```php
// ✅ Correct
class TelegramUpdateDto extends Data
{
    public int $updateId;
    public string $typeQuery;
    // ...

    public static function fromRequest(Request $request): ?self
    {
        // factory method
    }
}

// ❌ Incorrect — plain class or array used as DTO
class TelegramUpdateData
{
    public array $data;
}
```

---

## 5. Readonly DTOs for immutable data

Use `readonly` for DTOs that are never modified after creation.

```php
// ✅ Correct
readonly class MessageCreateDto
{
    public function __construct(
        public string $text,
        public int $chatId,
    ) {}
}

// ❌ Incorrect — mutable DTO for immutable concept
class MessageCreateDto
{
    public string $text;
    public int $chatId;
}
```

---

## 6. Enum rules

Use backed enums (`string` or `int`) for all status/type/error constants.

```php
// ✅ Correct
enum ButtonType: string
{
    case CALLBACK = 'callback';
    case URL      = 'url';
    case PHONE    = 'phone';
    case TEXT     = 'text';
}

// ❌ Incorrect — string constants instead of enum
class ButtonType
{
    const CALLBACK = 'callback';
    const URL      = 'url';
}
```

---

## 7. Single quotes for strings

Use single quotes for all strings that do not require variable interpolation or special characters.

```php
// ✅ Correct
$platform = 'telegram';
$method = 'sendMessage';

// ❌ Incorrect
$platform = "telegram";
$method = "sendMessage";
```

---

## 8. Array syntax

Always use short array syntax `[]`. Trailing commas in multiline arrays are mandatory.

```php
// ✅ Correct
$params = [
    'chat_id'    => $chatId,
    'text'       => $text,
    'parse_mode' => 'html',
];

// ❌ Incorrect — old array syntax, no trailing comma
$params = array(
    'chat_id' => $chatId,
    'text'    => $text
);
```

---

## 9. Import sorting

Imports must be sorted and unused imports removed before committing. Laravel Pint handles this automatically — always run it before committing.

```bash
docker exec -it pet ./vendor/bin/pint
```

---

## 10. Method length

Keep methods under 25 lines. Extract helper methods when a method grows larger.

```php
// ✅ Correct — focused method
public function handle(): void
{
    $this->botUser = BotUser::getOrCreateByTelegramUpdate($this->update);
    $this->messageParamsDTO = $this->buildMessageParams();
    SendTelegramMessageJob::dispatch($this->botUser->id, $this->messageParamsDTO);
}

// ❌ Incorrect — method doing too much
public function handle(): void
{
    $botUser = BotUser::where('chat_id', $this->update->chatId)->first();
    if (! $botUser) {
        $botUser = new BotUser();
        $botUser->chat_id = $this->update->chatId;
        $botUser->platform = 'telegram';
        $botUser->save();
    }
    // ... 30 more lines of setup and dispatch
}
```

---

## 11. Forbidden practices (project-specific)

In addition to the general rules in `rules/development/php.md` and `rules/development/laravel.md`:

- ❌ Calling `env()` anywhere except `config/*.php` files.
- ❌ Leaving `dd()`, `dump()`, `var_dump()`, or `ray()` calls in committed code.
- ❌ Using `BotUser::all()` — always query with specific constraints.
- ❌ Making HTTP calls to Telegram or VK APIs outside of `TelegramMethods::sendQueryTelegram()` and `VkMethods::sendQueryVk()`.
- ❌ Instantiating Services or Jobs with `new` inside other Services — use `dispatch()` for Jobs and dependency injection for Services.

---

## 12. Code formatting enforcement

The pre-commit hook runs Laravel Pint automatically. Do not bypass it.

```bash
# Run manually before committing
docker exec -it pet ./vendor/bin/pint

# Run static analysis before pushing
docker exec -it pet ./vendor/bin/phpstan analyse
```

---

## Checklist

- [ ] File starts with `declare(strict_types=1)`
- [ ] All public methods have PHPDoc
- [ ] All parameters and return types are explicit
- [ ] DTOs extend `Spatie\LaravelData\Data`
- [ ] Enums used instead of string/int constants
- [ ] Single quotes used for plain strings
- [ ] Trailing commas in multiline arrays
- [ ] Pint has been run — no formatting issues
- [ ] PHPStan level 6 passes
