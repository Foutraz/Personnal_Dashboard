<?php

namespace Functional\Gamification\Database\Seeders;

use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Illuminate\Database\Seeder;

class GamificationSeeder extends Seeder
{
    /**
     * Seed a player profile with a spread of ledger entries.
     */
    public function run(): void
    {
        $profile = PlayerProfile::factory()->create();

        XpEntry::factory()->count(12)->create(['user_id' => $profile->user_id]);
    }
}
