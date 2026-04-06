<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blacklisted_ips', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('ip', 50)->unique();
            $table->text('reason')->nullable();
            $table->string('source_env', 50)->nullable();
            $table->enum('source', ['manual', 'auto', 'sync'])->default('manual');
            $table->timestamp('expires_at')->nullable();
            $table->string('blocked_by', 255)->nullable();
            $table->string('log_entry_id', 26)->nullable(); // ULID ref, NOT a FK
            $table->timestamps();

            $table->index(['ip', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklisted_ips');
    }
};
