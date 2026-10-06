<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration adding the measure snapshot taken when a badge is awarded.
     */
    public function up(): void
    {
        if (Schema::hasColumn('badge_awards', 'measured_value')) {
            return;
        }

        Schema::table('badge_awards', function (Blueprint $table) {
            $table->double('measured_value')->nullable()->after('awarded_at');
        });
    }

    /**
     * Reverse the migration dropping the measure snapshot.
     */
    public function down(): void
    {
        Schema::table('badge_awards', function (Blueprint $table) {
            $table->dropColumn('measured_value');
        });
    }
};
