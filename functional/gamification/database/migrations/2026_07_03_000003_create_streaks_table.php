<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the streaks projection table.
     */
    public function up(): void
    {
        Schema::create('streaks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('domain', 32);
            $table->unsignedInteger('current_count')->default(0);
            $table->unsignedInteger('best_count')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'domain']);
        });
    }

    /**
     * Reverse the migration dropping the streaks table.
     */
    public function down(): void
    {
        Schema::dropIfExists('streaks');
    }
};
