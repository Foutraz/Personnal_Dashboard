<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the moto rides table.
     */
    public function up(): void
    {
        Schema::create('moto_rides', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('title');
            $table->timestamp('started_at');
            $table->unsignedInteger('duration');
            $table->decimal('distance', 10, 2);
            $table->string('weather_label')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'started_at']);
        });
    }

    /**
     * Reverse the migration dropping the moto rides table.
     */
    public function down(): void
    {
        Schema::dropIfExists('moto_rides');
    }
};
