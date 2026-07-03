<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the player_profiles projection table.
     */
    public function up(): void
    {
        Schema::create('player_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained('users');
            $table->unsignedBigInteger('total_xp')->default(0);
            $table->unsignedSmallInteger('level')->default(1);
            $table->timestamp('level_reached_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration dropping the player_profiles table.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_profiles');
    }
};
