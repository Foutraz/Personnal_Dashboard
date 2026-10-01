<?php

namespace Functional\Gamification\Database\Seeders;

use Functional\Gamification\Actions\SyncBadgeCatalogue;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Database\Seeder;

class GamificationSeeder extends Seeder
{
    /**
     * Seed the badge catalogue then a player profile with a spread of ledger entries and one live or broken streak per streak domain and a proposed, an accepted and a completed challenge on the current week.
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

        $this->seedChallenges($profile->user);
    }

    private function seedChallenges(User $user): void
    {
        Challenge::factory()
            ->for($user)
            ->forTemplate(ChallengeTemplateKey::SportDistance)
            ->create(['current_value' => faker()->number(0, 9)]);

        $acceptedTarget = faker()->number(3, 5);
        Challenge::factory()
            ->for($user)
            ->forTemplate(ChallengeTemplateKey::SportActivityCount)
            ->accepted()
            ->create([
                'baseline_value' => $acceptedTarget - 1,
                'target_value' => $acceptedTarget,
                'current_value' => faker()->number(1, $acceptedTarget - 1),
            ]);

        $completedTarget = faker()->number(20, 80);
        Challenge::factory()
            ->for($user)
            ->forTemplate(ChallengeTemplateKey::ExplorationCells)
            ->completed()
            ->create([
                'baseline_value' => $completedTarget - 5,
                'target_value' => $completedTarget,
                'current_value' => $completedTarget + faker()->number(0, 10),
            ]);
    }
}
