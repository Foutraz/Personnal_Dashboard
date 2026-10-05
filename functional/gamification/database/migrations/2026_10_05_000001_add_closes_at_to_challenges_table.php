<?php

use Functional\Gamification\Services\Dto\ChallengeSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('challenges', 'closes_at')) {
            Schema::table('challenges', function (Blueprint $table) {
                $table->timestamp('closes_at')->nullable()->after('ends_at');
            });
        }

        $graceHours = ChallengeSettings::fromConfig()->closingGraceHours;

        foreach (DB::table('challenges')->whereNull('closes_at')->distinct()->pluck('ends_at') as $endsAt) {
            DB::table('challenges')
                ->whereNull('closes_at')
                ->where('ends_at', $endsAt)
                ->update(['closes_at' => Carbon::parse($endsAt, 'UTC')->addHours($graceHours)->toDateTimeString()]);
        }

        Schema::table('challenges', function (Blueprint $table) {
            $table->timestamp('closes_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn('closes_at');
        });
    }
};
