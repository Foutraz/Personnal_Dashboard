<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the bank transactions table.
     */
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bank_account_id')->constrained('bank_accounts');
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('external_id');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->timestamp('booked_at');
            $table->string('description')->nullable();
            $table->string('counterparty')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
            $table->unique(['bank_account_id', 'external_id']);
        });
    }

    /**
     * Reverse the migration dropping the bank transactions table.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
