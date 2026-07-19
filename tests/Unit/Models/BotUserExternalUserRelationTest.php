<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\BotUser;
use App\Models\ExternalSource;
use App\Models\ExternalUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression cover for the externalUser relation after `bot_users.chat_id` was
 * widened to a string and the foreign key moved to `external_user_id`.
 *
 * These assert on the generated SQL, not merely on the absence of an exception:
 * the suite runs on SQLite, which compares a string to an integer happily, while
 * production PostgreSQL rejects it. Both historical failures were invisible to a
 * pass/fail check here:
 *   - `where id in ('u2i-…')` against bigint  → 22P02
 *   - `chat_id = external_users.id` (varchar = bigint) → 42883
 */
class BotUserExternalUserRelationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: ExternalSource, 1: ExternalUser}
     */
    private function makeExternalUser(string $source = 'widget_src', string $externalId = 'visitor-1'): array
    {
        $externalSource = ExternalSource::create(['name' => $source]);
        $externalUser = ExternalUser::create(['external_id' => $externalId, 'source' => $source]);

        return [$externalSource, $externalUser];
    }

    /**
     * @param string $needle
     *
     * @return array<int, mixed>
     */
    private function captureBindingsFor(string $needle, callable $run): array
    {
        $bindings = [];

        DB::listen(function ($query) use (&$bindings, $needle): void {
            if (str_contains($query->sql, $needle)) {
                $bindings = array_merge($bindings, $query->bindings);
            }
        });

        $run();

        return $bindings;
    }

    public function test_non_numeric_chat_id_is_never_bound_against_external_users(): void
    {
        $botUser = BotUser::create(['chat_id' => 'u2i-abc-123', 'platform' => 'avito']);

        $bindings = $this->captureBindingsFor('external_users', function () use ($botUser): void {
            BotUser::with('externalUser')->find($botUser->id);
        });

        $this->assertNotContains('u2i-abc-123', $bindings);
    }

    public function test_relation_resolves_through_the_dedicated_column(): void
    {
        [, $externalUser] = $this->makeExternalUser();

        $botUser = BotUser::create([
            'chat_id' => (string) $externalUser->id,
            'external_user_id' => $externalUser->id,
            'platform' => $externalUser->source,
        ]);

        $loaded = BotUser::with('externalUser')->find($botUser->id);

        $this->assertNotNull($loaded->externalUser);
        $this->assertSame($externalUser->id, $loaded->externalUser->id);
    }

    public function test_where_has_does_not_compare_chat_id_to_external_users_id(): void
    {
        // The 42883 case: this join fired even with zero Avito rows, because it
        // is a column type mismatch rather than a data problem.
        [, $externalUser] = $this->makeExternalUser('site_src', 'v-9');

        BotUser::create([
            'chat_id' => (string) $externalUser->id,
            'external_user_id' => $externalUser->id,
            'platform' => $externalUser->source,
        ]);

        $sql = BotUser::whereHas('externalUser', function ($q): void {
            $q->where('source', 'site_src');
        })->toSql();

        $this->assertStringNotContainsString('"bot_users"."chat_id" = "external_users"."id"', $sql);
        $this->assertStringContainsString('external_user_id', $sql);
    }

    public function test_mixed_result_set_keeps_the_non_numeric_id_out_of_the_query(): void
    {
        [, $externalUser] = $this->makeExternalUser('mixed_src', 'v-42');

        BotUser::create([
            'chat_id' => (string) $externalUser->id,
            'external_user_id' => $externalUser->id,
            'platform' => $externalUser->source,
        ]);
        BotUser::create(['chat_id' => 'u2i-mixed-42', 'platform' => 'avito']);

        $bindings = $this->captureBindingsFor('external_users', function (): void {
            BotUser::with('externalUser')->get();
        });

        $this->assertNotContains('u2i-mixed-42', $bindings);
        $this->assertContains($externalUser->id, $bindings);
    }

    public function test_external_bot_user_is_created_and_found_by_the_new_key(): void
    {
        [, $externalUser] = $this->makeExternalUser('api_src', 'ext-77');

        $created = (new BotUser())->getExternalBotUser('ext-77', 'api_src');
        $this->assertNull($created, 'Nothing exists yet.');

        $botUser = BotUser::create([
            'chat_id' => (string) $externalUser->id,
            'external_user_id' => $externalUser->id,
            'platform' => 'api_src',
        ]);

        $found = (new BotUser())->getExternalBotUser('ext-77', 'api_src');

        $this->assertNotNull($found);
        $this->assertSame($botUser->id, $found->id);
    }
}
