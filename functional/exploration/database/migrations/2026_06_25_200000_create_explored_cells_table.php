<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the explored cells table.
     */
    public function up(): void
    {
        Schema::create('explored_cells', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('cell_key');
            $table->decimal('lat', 10, 6);
            $table->decimal('lng', 10, 6);
            $table->unsignedInteger('visit_count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['user_id', 'cell_key']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migration dropping the explored cells table.
     */
    public function down(): void
    {
        Schema::dropIfExists('explored_cells');
    }
};
