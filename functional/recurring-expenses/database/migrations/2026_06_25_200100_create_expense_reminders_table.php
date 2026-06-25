<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the expense reminders table.
     */
    public function up(): void
    {
        Schema::create('expense_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('recurring_expense_id')->constrained('recurring_expenses');
            $table->timestamp('due_at');
            $table->boolean('notified')->default(false);
            $table->timestamps();
            $table->unique(['recurring_expense_id', 'due_at']);
            $table->index(['notified', 'due_at']);
        });
    }

    /**
     * Reverse the migration dropping the expense reminders table.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_reminders');
    }
};
