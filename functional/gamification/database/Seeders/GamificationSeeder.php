<?php

namespace Functional\Gamification\Database\Seeders;

use Functional\Gamification\Actions\SyncBadgeCatalogue;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Illuminate\Database\Seeder;

class GamificationSeeder extends Seeder
{
    /**
     * Seed the badge catalogue then a player profile with a spread of ledger entries and one live or broken streak per streak domain.
     */
    public function run(): void
    {
        app(SyncBadgeCatalogue::class)->handle();

        $profile = PlayerProfile::factory()->create();

        XpEntry::factory()->count(12)->create(['user_id' => $profile->user_id]);

        foreach (GamificationDomain::streakDomains() as $index => $domain) {
            Streak::factory()
                ->for($profile->user)
                ->when($index % 2 === 1, fn ($factory) => $factory->broken())
                ->create(['domain' => $domain]);
        }
    }
}
