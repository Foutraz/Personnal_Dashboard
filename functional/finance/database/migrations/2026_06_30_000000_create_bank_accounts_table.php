<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the bank accounts table.
     */
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('integration_connection_id')->constrained('integration_connections');
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('external_id');
            $table->string('institution_id')->nullable();
            $table->string('name')->nullable();
            $table->string('iban')->nullable();
            $table->string('currency', 3);
            $table->decimal('balance', 18, 2)->default(0);
            $table->timestamp('balance_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
            $table->unique(['integration_connection_id', 'external_id']);
        });
    }

    /**
     * Reverse the migration dropping the bank accounts table.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
