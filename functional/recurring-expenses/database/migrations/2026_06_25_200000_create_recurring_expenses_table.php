<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the recurring expenses table.
     */
    public function up(): void
    {
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('category');
            $table->string('frequency');
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('next_due_at');
            $table->boolean('active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'next_due_at']);
        });
    }

    /**
     * Reverse the migration dropping the recurring expenses table.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};
