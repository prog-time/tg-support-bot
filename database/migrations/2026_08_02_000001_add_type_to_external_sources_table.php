<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('external_sources', function (Blueprint $table) {
            // Distinguishes the admin-panel form/flow for a source: 'api' (bearer
            // token + webhook URL) or 'widget' (public key for the JS live-chat
            // widget). Existing rows all predate this column and were created
            // through the API-source flow, so the DB default backfills them as 'api'.
            $table->string('type')->default('api')->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('external_sources', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
