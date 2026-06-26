<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the calendar events table.
     */
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('integration_connection_id')->constrained('integration_connections');
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('provider');
            $table->string('external_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('external_link')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['integration_connection_id', 'external_id']);
            $table->index(['user_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migration dropping the calendar events table.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
