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
        Schema::create('external_source_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_source_id')->constrained()->onDelete('cascade');
            $table->string('token', 64)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_source_access_tokens');
    }
};
