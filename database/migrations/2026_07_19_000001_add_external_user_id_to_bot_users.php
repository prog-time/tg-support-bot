<?php

use App\Models\BotUser;
use App\Models\ExternalUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Give External Sources users a real, typed foreign key instead of reusing
     * `bot_users.chat_id`.
     *
     * `chat_id` carried two incompatible meanings: the conversation id on the
     * platform (telegram/vk/max/avito) and, for External Sources users, the
     * primary key of `external_users`. That worked only while the column was
     * numeric. Once it was widened to a string
     * (see 2026_07_06_000001_change_bot_users_chat_id_to_string), PostgreSQL
     * started rejecting both uses of the relation:
     *   - eager/lazy load → `where id in ('u2i-…')`  → SQLSTATE 22P02
     *   - whereHas/join   → `chat_id = external_users.id` (varchar = bigint)
     *                                                 → SQLSTATE 42883
     * The second one fires even with no Avito rows at all, because it is a type
     * mismatch rather than a data problem. SQLite compares the two loosely, so
     * the test suite could not see either failure.
     *
     * `chat_id` is left populated for External Sources rows so existing reads
     * keep working; `external_user_id` is the authoritative key from now on.
     */
    public function up(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->unsignedBigInteger('external_user_id')->nullable()->after('chat_id')->index();
        });

        $this->backfill();
    }

    /**
     * Populate the new column from the (numeric) chat_id of External Sources rows.
     *
     * Driven from `external_users` and matched on BOTH id and source→platform.
     * Matching on the id alone would be wrong: `external_users.id` values are
     * small sequential integers, so a Telegram user whose chat_id happens to be
     * e.g. 5 would be silently linked to external user #5.
     *
     * @return void
     */
    private function backfill(): void
    {
        ExternalUser::query()->chunkById(200, function ($externalUsers): void {
            foreach ($externalUsers as $externalUser) {
                BotUser::query()
                    ->where('platform', $externalUser->source)
                    ->where('chat_id', (string) $externalUser->id)
                    ->update(['external_user_id' => $externalUser->id]);
            }
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->dropColumn('external_user_id');
        });
    }
};
