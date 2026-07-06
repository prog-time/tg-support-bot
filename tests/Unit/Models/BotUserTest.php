<?php

namespace Tests\Unit\Models;

use App\Jobs\EnrichBotUserProfileJob;
use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BotUserTest extends TestCase
{
    use RefreshDatabase;

    // ── Helper: build a minimal TelegramUpdateDto ──────────────────────────────

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeDto(array $overrides = []): TelegramUpdateDto
    {
        return new TelegramUpdateDto(
            updateId: 1,
            typeQuery: 'message',
            aiTechMessage: false,
            typeSource: $overrides['typeSource'] ?? 'private',
            isBot: false,
            chatId: $overrides['chatId'] ?? 100,
            username: $overrides['username'] ?? null,
            displayName: $overrides['displayName'] ?? null,
        );
    }

    // ── Profile sync on creation ───────────────────────────────────────────────

    public function test_sync_display_name_on_new_user_creation(): void
    {
        Queue::fake();

        $dto = $this->makeDto(['chatId' => 200, 'displayName' => 'Ivan Petrov', 'username' => 'ivan_p']);

        $botUser = BotUser::getOrCreateByTelegramUpdate($dto);

        $this->assertNotNull($botUser);
        $this->assertSame('Ivan Petrov', $botUser->display_name);
        $this->assertSame('ivan_p', $botUser->username);
        $this->assertTrue($botUser->wasRecentlyCreated);

        Queue::assertPushed(EnrichBotUserProfileJob::class);
    }

    // ── Opportunistic update when name changes ─────────────────────────────────

    public function test_opportunistic_update_when_name_changes(): void
    {
        Queue::fake();

        // Pre-existing user with old name.
        BotUser::create([
            'chat_id' => 300,
            'platform' => 'telegram',
            'display_name' => 'Old Name',
            'username' => 'old_handle',
        ]);

        $dto = $this->makeDto(['chatId' => 300, 'displayName' => 'New Name', 'username' => 'new_handle']);

        $botUser = BotUser::getOrCreateByTelegramUpdate($dto);

        $this->assertNotNull($botUser);
        $botUser->refresh();
        $this->assertSame('New Name', $botUser->display_name);
        $this->assertSame('new_handle', $botUser->username);
    }

    // ── No write when name is unchanged ───────────────────────────────────────

    public function test_no_write_when_name_unchanged(): void
    {
        Queue::fake();

        BotUser::create([
            'chat_id' => 400,
            'platform' => 'telegram',
            'display_name' => 'Same Name',
            'username' => 'same_handle',
        ]);

        $dto = $this->makeDto(['chatId' => 400, 'displayName' => 'Same Name', 'username' => 'same_handle']);

        DB::enableQueryLog();
        BotUser::getOrCreateByTelegramUpdate($dto);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $updateQueries = array_filter($log, static fn ($q) => str_contains(strtolower($q['query']), 'update'));

        // Should only be the firstOrCreate SELECT + no UPDATE for profile fields.
        $profileUpdates = array_filter($updateQueries, static fn ($q) => str_contains($q['query'], 'display_name'));
        $this->assertCount(0, $profileUpdates);
    }

    // ── String chat_id (pluggable platforms: Avito "u2i-...", WhatsApp, …) ─────

    public function test_string_chat_id_round_trips_without_coercion(): void
    {
        $botUser = BotUser::create(['chat_id' => 'u2i-abc123', 'platform' => 'avito']);

        $fresh = BotUser::findOrFail($botUser->id);

        // Persisted and read back as the exact string — no int coercion, no
        // dropped prefix (the whole point of widening chat_id to string).
        $this->assertSame('u2i-abc123', $fresh->chat_id);
        $this->assertSame($botUser->id, BotUser::where('chat_id', 'u2i-abc123')->first()?->id);
    }

    // ── external_source relation regression across the type change ─────────────
    // bot_users.chat_id doubles as the FK to external_users.id (bigint). After
    // widening chat_id to string, the varchar ↔ bigint join must still resolve.

    public function test_external_user_relation_resolves_after_chat_id_widened_to_string(): void
    {
        $externalUser = \App\Models\ExternalUser::create([
            'external_id' => 555,
            'source' => 'regression-source',
        ]);

        $botUser = BotUser::create([
            'chat_id' => (string) $externalUser->id,
            'platform' => 'external_source',
        ]);

        // Forward relation (hasOne ExternalUser 'id' <- 'chat_id') joins the now
        // varchar chat_id against the bigint external_users.id — must still resolve.
        $this->assertTrue($botUser->externalUser->is($externalUser));
    }
}
