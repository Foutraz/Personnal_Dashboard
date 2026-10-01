<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Database\Seeders\GamificationSeeder;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Services\GamificationCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GamificationSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_live_and_broken_streaks_for_the_seeded_player(): void
    {
        $this->seed(GamificationSeeder::class);

        $profile = PlayerProfile::query()->sole();
        $streaks = Streak::query()->whereBelongsTo($profile->user)->get();
        $calendar = $this->app->make(GamificationCalendar::class);

        $this->assertCount(count(GamificationDomain::streakDomains()), $streaks);
        $this->assertTrue($streaks->contains(fn (Streak $streak): bool => $calendar->isStreakAlive($streak->last_activity_date)));
        $this->assertTrue($streaks->contains(fn (Streak $streak): bool => ! $calendar->isStreakAlive($streak->last_activity_date)));
    }

    #[Test]
    public function it_seeds_the_full_badge_catalogue(): void
    {
        $this->seed(GamificationSeeder::class);

        $this->assertSame(30, Badge::query()->count());
    }
}
