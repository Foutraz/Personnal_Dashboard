<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the investment transactions table.
     */
    public function up(): void
    {
        Schema::create('investment_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('position_id')->constrained('positions');
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('type');
            $table->decimal('quantity', 18, 8);
            $table->decimal('unit_price', 18, 8);
            $table->timestamp('executed_at');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'executed_at']);
            $table->index(['position_id', 'executed_at']);
        });
    }

    /**
     * Reverse the migration dropping the investment transactions table.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_transactions');
    }
};
