# TG Support Bot — Rules Index

> Read this file first. Always.

> **Version:** 1.0.0
> **Context:** This is the entry point for every AI agent working on this project. Read this file before touching any code.

## What this application does

TG Support Bot is a Laravel 12 application that provides a multi-platform customer support system via Telegram and VK. Incoming messages from end users arrive via Telegram (private) or VK, and are routed to a support group in Telegram as forum topics. Support operators reply inside the topic, and the system forwards answers back to the original platform. The application also exposes a REST API for external third-party systems to inject and receive messages via webhooks.

## Technology stack

| Component       | Technology              | Version  |
|---|---|---|
| Language        | PHP                     | 8.2+     |
| Framework       | Laravel                 | ^12.0    |
| Database        | PostgreSQL              | 15+      |
| Queue driver    | sync (configurable)     | —        |
| Cache           | file (configurable)     | —        |
| DTO layer       | spatie/laravel-data     | ^4.14    |
| API docs        | darkaonline/l5-swagger  | ^9.0     |
| Logging         | Loki (via monolog)      | ^3.9     |
| Error tracking  | Sentry                  | ^4.15    |
| Static analysis | PHPStan (Larastan)      | level 6  |
| Code style      | Laravel Pint            | ^1.13    |
| Tests           | PHPUnit                 | ^11.5    |

## Rules navigation

| File | What it covers |
|---|---|
| `_meta/how-to-write-rules.md`       | Standards every rules file must meet |
| `database/schema.md`                | Full ERD and table-by-table reference |
| `database/conventions.md`           | DB naming, indexing, migration rules for this project |
| `domain/overview.md`                | High-level map of all business domains |
| `domain/messaging.md`               | Core message routing between platforms |
| `domain/bot-users.md`               | BotUser lifecycle, banning, topic management |
| `domain/external-sources.md`        | External REST API integration (third-party sources) |
| `domain/ai-assistant.md`            | AI assistant feature (GigaChat / DeepSeek / OpenAI) |
| `api/overview.md`                   | API design principles |
| `api/endpoints.md`                  | Full endpoint catalogue |
| `api/responses.md`                  | Response shapes, error formats, pagination |
| `auth/rules.md`                     | Authentication and authorization rules |
| `architecture/overview.md`          | Layers, patterns, architectural decisions |
| `architecture/data-flow.md`         | How data moves through the system |
| `testing/rules.md`                  | Project-specific testing rules |
| `conventions/naming.md`             | Project naming conventions |
| `conventions/code-style.md`         | Code style rules for this project |

## Critical rules

- **Never commit `.env` files** — they contain real credentials.
- **Always run Pint before committing** — pre-commit hook enforces it.
- **PHPStan level 6 must pass** — pre-push hook enforces it.
- **Tests must pass before push** — pre-push hook runs PHPUnit.
- **Controllers are thin** — only receive the request, delegate to a Service, return a response.
- **Use DTOs for all inter-layer data transfer** — never pass plain arrays between Services, Actions, and Jobs.
- **Jobs are the only place for async operations** — all external API calls (Telegram, VK) go through a Job.
- **New functionality must be covered by tests** — no exceptions.
- **Every new business rule must be documented** in the relevant `domain/*.md` file.
- **Never call `env()` outside `config/` files** — always use `config('key')`.

## AI agent onboarding checklist

- [ ] Read this file
- [ ] Read `database/schema.md`
- [ ] Read `domain/overview.md`
- [ ] Read `architecture/overview.md`
- [ ] Read `architecture/data-flow.md`
- [ ] Read the relevant `domain/{name}.md` for the area you are modifying
- [ ] Read `conventions/naming.md`
- [ ] Run `docker exec -it pet php artisan test` to verify the test suite is green before making changes

## Keeping rules up to date

Rules files are living documents. They must be updated when:

- A new table or column is added → update `database/schema.md`
- A new business rule is implemented → add it to the relevant `domain/*.md`
- A new endpoint is added → add it to `api/endpoints.md`
- A new role or permission is introduced → update `auth/rules.md`
- An architectural decision is made → document it in `architecture/overview.md`

**Rule:** Never merge a PR that introduces a new business rule, endpoint, or schema change without a corresponding update to the relevant rules file.

## Checklist

- [ ] This file links to every file in `rules/`
- [ ] Critical rules are complete and non-negotiable
- [ ] Technology stack is accurate
- [ ] Onboarding checklist is actionable
