# Upgrade Guide

## Upgrading from 9.x: separate AI bot removed

**Breaking change.** The separate Telegram AI bot no longer exists. AI drafts and
replies are published to the topic by the main bot with the `🤖 AI:` label, and the
main bot handles the draft buttons. Removed: `POST /api/ai-bot/webhook`,
`/admin/settings/integrations/telegram_ai`, `php artisan ai-bot:set-webhook` and
the `telegram_ai.*` settings. Installations without an AI bot now get AI messages
in the group as soon as the main bot and `telegram.group_id` are configured.

Only the author can edit a Telegram message, so drafts published by the old AI bot
must be closed with its token. Upgrade in this order:

```bash
# 1. Disable AI at /admin/settings/ai so new drafts do not mix with old ones.

# 2. Deploy the release and run migrations as usual.

# 3. Retire the AI bot. Idempotent: if Telegram is unavailable it exits with an
#    error and keeps the settings, run it again.
php artisan ai-bot:retire

# 4. Enable AI at /admin/settings/ai.

# 5. Remove the AI bot from the group and, optionally, revoke its token.
```

`ai-bot:retire` deletes old pending drafts from the topics (or removes their buttons
when Telegram no longer allows deletion). They stay pending in `/admin/chats`, they
are not published again. Until the command runs, the old buttons do nothing.

Rolling back to the previous version requires entering the AI bot token and secret
again and running `php artisan ai-bot:set-webhook`.

## Upgrading to 8.0.0 from 7.x

Release 8.0.0 is a **major release** with breaking changes to configuration and
the manager-interface model. Read this guide fully before deploying to a running
environment.

### Breaking changes

1. **Channel & AI credentials moved from `.env` to the database.**
   All `telegram.*`, `telegram_ai.*`, `vk.*`, `max.*` and AI provider
   credentials/behaviour keys are now stored exclusively in the `settings` table
   and read via `SettingsService` (registry entries declare `config => null`, so
   there is **no `.env`/`config()` fallback**). After upgrading, these values
   return `null` until they are re-entered through the admin panel — the bot will
   not send or receive messages and AI replies will fail until then.

2. **`MANAGER_INTERFACE` removed.** The manager-interface mode switch and the
   `ManagerInterfaceContract` abstraction were removed. The admin panel
   (`/admin/chats`) is now always-active; incoming and outgoing messages persist
   to the panel independently of the Telegram group. Remove `MANAGER_INTERFACE`
   from your `.env` (it is ignored).

3. **Filament removed.** The admin panel is now custom Livewire + standard
   Laravel `web`/`auth` guard. Routes are unchanged for end users
   (`/admin/login`, `/admin/chats`, `/admin/settings/*`).

4. **Telescope basic-auth removed.** The Telescope dashboard is now gated by
   session-based admin auth (logged-in admin), not HTTP Basic auth. Remove
   `TELESCOPE_AUTH_USER` / `TELESCOPE_AUTH_PASSWORD` from your `.env`.

5. **Sentry and the Loki/Grafana logging stack removed.** Application logs now
   go to rotating files (`storage/logs/`), viewable via `php artisan pail` or
   Telescope. Drop the corresponding `.env` keys and infrastructure.

### Deployment steps

```bash
# 1. Pull the release and install dependencies
composer install --no-dev --optimize-autoloader

# 2. Run the new migrations (all additive: nullable / default columns)
php artisan migrate

# 3. Clear and rebuild caches
php artisan config:clear
php artisan cache:clear

# 4. Re-enter all secrets via the admin panel (REQUIRED — no .env fallback):
#    /admin/settings/integrations  → Telegram, Telegram AI bot, VK, MAX tokens
#    /admin/settings/ai/{provider} → OpenAI / DeepSeek / GigaChat credentials
#    /admin/settings/general       → Telegram group_id, topic-name template

# 5. Re-register webhooks once credentials are saved
php artisan ai-bot:set-webhook
```

### Migration safety

All ten new migrations are additive — new columns are `nullable()` or carry a
`default()`, no data backfill is required, and each defines a `down()`.

> Rollback caveat: `add_status_to_ai_messages_table` restores
> `ai_messages.message_id` to `NOT NULL` in `down()`. If any AI draft rows have a
> `NULL message_id` at rollback time, the rollback will fail. This affects
> rollback only, never the forward migration.
