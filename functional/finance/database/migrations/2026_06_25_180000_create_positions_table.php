<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the positions table.
     */
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('asset_symbol');
            $table->string('asset_name');
            $table->string('asset_type');
            $table->decimal('quantity', 18, 8)->default(0);
            $table->decimal('average_buy_price', 18, 8)->default(0);
            $table->decimal('current_price', 18, 8)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'asset_symbol']);
        });
    }

    /**
     * Reverse the migration dropping the positions table.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
