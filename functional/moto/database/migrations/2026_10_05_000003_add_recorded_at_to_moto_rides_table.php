<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration adding the server-stamped recording instant to the moto rides.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('moto_rides', 'recorded_at')) {
            Schema::table('moto_rides', function (Blueprint $table) {
                $table->timestamp('recorded_at')->nullable()->after('created_at');
            });
        }

        $creationColumn = DB::getQueryGrammar()->wrap('created_at');

        DB::table('moto_rides')
            ->whereNull('recorded_at')
            ->whereNotNull('created_at')
            ->update(['recorded_at' => DB::raw($creationColumn)]);

        DB::table('moto_rides')
            ->whereNull('recorded_at')
            ->update(['recorded_at' => now()->toDateTimeString()]);

        Schema::table('moto_rides', function (Blueprint $table) {
            $table->timestamp('recorded_at')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migration dropping the recording instant.
     */
    public function down(): void
    {
        Schema::table('moto_rides', function (Blueprint $table) {
            $table->dropColumn('recorded_at');
        });
    }
};
