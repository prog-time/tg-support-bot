<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Widen bot_users.chat_id from unsignedBigInteger to string so the core can
     * natively store conversation ids of pluggable platforms whose id is a
     * string (Avito "u2i-...", WhatsApp, …). Numeric ids (telegram/vk/max and the
     * external_source FK to external_users.id) convert cleanly to text; the
     * existing index is rebuilt by the database for the new type.
     */
    public function up(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->string('chat_id')->change();
        });
    }

    /**
     * Revert to unsignedBigInteger.
     *
     * WARNING: irreversible once any non-numeric chat_id exists (e.g. an Avito
     * "u2i-..." id) — casting varchar → bigint will fail. Safe only while every
     * chat_id is numeric (the pre-Avito state).
     */
    public function down(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->unsignedBigInteger('chat_id')->change();
        });
    }
};
