<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the challenges table.
     */
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('week_key', 8);
            $table->string('template_key', 64);
            $table->string('domain', 32);
            $table->string('metric', 64);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->decimal('baseline_value', 14, 2);
            $table->decimal('target_value', 14, 2);
            $table->decimal('current_value', 14, 2)->default(0);
            $table->unsignedSmallInteger('xp_reward');
            $table->string('status', 16);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'week_key', 'template_key'], 'challenges_week_template_unique');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migration dropping the challenges table.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
