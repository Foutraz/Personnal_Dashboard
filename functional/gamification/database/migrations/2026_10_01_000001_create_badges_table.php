<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the badge catalogue table.
     */
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->string('rule_key', 64);
            $table->string('domain', 32);
            $table->string('tier', 16);
            $table->decimal('threshold', 14, 2);
            $table->unsignedInteger('xp_reward');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration dropping the badges table.
     */
    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
