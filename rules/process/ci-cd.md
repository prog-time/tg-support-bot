# CI/CD Rules

> **Purpose:** Ensure every change passes automated checks and deployments consistently. Guarantee that AI-generated code is validated and deployable before reaching production.
> **Context:** Read this file before touching any CI/CD pipeline, merge request, or deployment process.
> **Version:** 1.0

---

## 1. Core Principle

Every change must pass automated checks before merging or deploying.

- CI/CD is not optional
- No manual deployments without pipeline completion
- Broken pipelines must be fixed immediately
- Never disable checks to speed up delivery
- Never use `--no-verify` to bypass git hooks

---

## 2. Git Hooks (Local)

The project keeps a single local hook managed in `linting/`:

| Hook | Script | What it does |
|---|---|---|
| `pre-push` | `linting/pre-push-check.sh` | Runs PHPStan level 6 + PHPUnit |

The `pre-commit` and `prepare-commit-msg` hooks have been removed. All checks (Pint, PHPStan, tests, hadolint, shellcheck) are covered by CI (`.github/workflows/ci.yml`).

**Pint coverage gap (conscious decision):** Pint is not enforced by the local `pre-push` hook and has no dedicated CI job. Run `vendor/bin/pint` manually before committing to keep formatting clean.

### Hook Compliance Rules

- PHPStan level 6 must pass before every push
- All tests must pass before every push
- If a hook fails, fix the issue — do not bypass

```bash
# ✅ Correct — run Pint manually before committing (no local pre-commit hook)
vendor/bin/pint
git add -p
git commit -m "issues-42 | add feature"

# ❌ Incorrect — bypass hooks
git push --no-verify
```

---

## 3. Development Commands

### Install dependencies
```bash
docker compose up -d --build
```
On the `app` container's first boot, `docker/scripts/entrypoint.sh` runs `composer install` and `npm ci && npm run build` automatically when `vendor/` or `public/build/manifest.json` are missing — see README, «Установка через Docker Compose: с нуля» for the full first-time setup (directory ownership, `.env`, migrations).

### Code formatting (must run before commit)
```bash
docker exec -it pet ./vendor/bin/pint
```

### Static analysis (must pass before push)
```bash
docker exec -it pet ./vendor/bin/phpstan analyse
```

### Run all tests (must pass before push)
```bash
docker exec -it pet php artisan test
# or
docker exec -it pet ./vendor/bin/phpunit
```

### Run specific test
```bash
docker exec -it pet php artisan test --filter=TestName
```

---

## 4. PHPStan Rules

- Static analysis level: **6**
- Configuration: `phpstan.neon`
- Must pass with 0 errors before merge
- PHPStan uses `nunomaduro/larastan` for Laravel-specific rules

```bash
# ✅ Correct — run full analysis
docker exec -it pet ./vendor/bin/phpstan analyse

# ❌ Incorrect — skipping analysis
git push  # without running PHPStan
```

---

## 5. Testing in CI

Tests run with SQLite in-memory database:

```xml
<!-- phpunit.xml -->
<env name="APP_ENV" value="testing"/>
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="CACHE_STORE" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
```

All tests must pass against this configuration.

---

## 6. Docker Infrastructure

The application runs in Docker. All development commands must be run inside the container.

| Container | Name | Purpose |
|---|---|---|
| PHP-FPM | `pet` | Main application container |
| PostgreSQL | `pgdb` | Database |
| Nginx | `nginx` | Web server |
| Queue worker | `queue` | `php artisan queue:work` |

Queue worker config:
- Tries: 3
- Timeout: 90 seconds
- Runs: `php artisan queue:work`

---

## 7. Rules for AI Involvement in CI/CD

- The AI agent must never merge directly into protected branches (`main`, `master`)
- The AI agent may only create feature branches (`issues-{number}`)
- The AI agent must run Pint and PHPStan locally before committing
- The AI agent must not hardcode environment variables in any config
- The AI agent must not modify `docker-compose.yml` without explicit user approval

---

## 8. Branch and Merge Rules

```
# Branch naming
issues-{number}
issues-{number}-{brief-description}

# Examples
issues-42
issues-85-fix-banned-user-reply
```

- Feature branches must be based on `main`
- Merges to `main` go through Pull Requests only
- PRs require passing git hooks (pre-push)
- Never force-push to `main`

---

## 9. Forbidden Actions

- ❌ `git push --no-verify` (bypass pre-push)
- ❌ Hardcoding environment variables in code
- ❌ Committing `.env` files
- ❌ Committing API keys or secrets
- ❌ Force-pushing to `main`
- ❌ Merging with PHPStan errors
- ❌ Merging with failing tests

---

## 10. Definition of Ready to Merge

A branch is ready to merge only if:

- [ ] Pint formatting passes (0 changes needed)
- [ ] PHPStan level 6 passes (0 errors)
- [ ] All tests pass (`php artisan test`)
- [ ] No secrets committed
- [ ] Branch based on latest `main`
- [ ] Rules documentation updated if behavior changed
- [ ] Commit messages follow `issues-{number} | {description}` format

---

## Checklist

- [ ] Pint formatting passes
- [ ] PHPStan level 6 passes
- [ ] All tests pass
- [ ] No secrets in committed files
- [ ] Branch named correctly
- [ ] Commit messages formatted correctly
- [ ] No hooks bypassed
- [ ] Documentation updated if pipeline behavior changed
