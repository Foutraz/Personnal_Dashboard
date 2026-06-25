<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the tasks table.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority');
            $table->string('status');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migration dropping the tasks table.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
