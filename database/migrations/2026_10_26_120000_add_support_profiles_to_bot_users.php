<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->string('custom_name', 150)->nullable();
            $table->text('internal_note')->nullable();
            $table->json('support_tags')->nullable();
        });
    }

    public function down(): void {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->dropColumn(['custom_name', 'internal_note', 'support_tags']);
        });
    }
};
