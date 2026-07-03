<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the xp_entries ledger table.
     */
    public function up(): void
    {
        Schema::create('xp_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('domain');
            $table->string('rule_key');
            $table->string('source_type');
            $table->string('source_id');
            $table->unsignedSmallInteger('points');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['user_id', 'rule_key', 'source_type', 'source_id'], 'xp_entries_award_unique');
            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'domain']);
        });
    }

    /**
     * Reverse the migration dropping the xp_entries table.
     */
    public function down(): void
    {
        Schema::dropIfExists('xp_entries');
    }
};
