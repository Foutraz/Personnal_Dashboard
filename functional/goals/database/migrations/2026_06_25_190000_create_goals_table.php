<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the goals table.
     */
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('metric');
            $table->decimal('target_value', 18, 4);
            $table->decimal('manual_current_value', 18, 4)->nullable();
            $table->string('unit')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migration dropping the goals table.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
