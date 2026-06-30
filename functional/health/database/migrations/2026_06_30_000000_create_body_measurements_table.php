<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the body measurements table.
     */
    public function up(): void
    {
        Schema::create('body_measurements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('integration_connection_id')->constrained('integration_connections');
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('external_id');
            $table->string('type');
            $table->float('value');
            $table->string('unit')->nullable();
            $table->timestamp('measured_at');
            $table->json('raw')->nullable();
            $table->timestamps();
            $table->unique(['integration_connection_id', 'external_id', 'type']);
        });
    }

    /**
     * Reverse the migration dropping the body measurements table.
     */
    public function down(): void
    {
        Schema::dropIfExists('body_measurements');
    }
};
