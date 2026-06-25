<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the task reminders table.
     */
    public function up(): void
    {
        Schema::create('task_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('task_id')->constrained('tasks');
            $table->timestamp('remind_at');
            $table->boolean('sent')->default(false);
            $table->timestamps();
            $table->index(['sent', 'remind_at']);
        });
    }

    /**
     * Reverse the migration dropping the task reminders table.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_reminders');
    }
};
