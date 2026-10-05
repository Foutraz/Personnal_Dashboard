<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Database\Seeders\GamificationSeeder;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\Challenge;
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

    #[Test]
    public function it_seeds_a_proposed_an_accepted_and_a_completed_challenge_on_the_current_week(): void
    {
        $this->seed(GamificationSeeder::class);

        $profile = PlayerProfile::query()->sole();
        $challenges = Challenge::query()->whereBelongsTo($profile->user)->get();
        $currentWeekKey = $this->app->make(GamificationCalendar::class)->currentWeek()->key();

        $this->assertCount(3, $challenges);
        $this->assertSame([$currentWeekKey], $challenges->pluck('week_key')->unique()->values()->all());
        $this->assertEqualsCanonicalizing(
            [ChallengeStatus::Proposed, ChallengeStatus::Accepted, ChallengeStatus::Completed],
            $challenges->pluck('status')->all(),
        );
    }

    #[Test]
    public function it_seeds_challenges_with_distinct_templates_and_progress_that_fits_their_status(): void
    {
        $this->seed(GamificationSeeder::class);

        $challenges = Challenge::query()->get();
        $accepted = $challenges->firstWhere('status', ChallengeStatus::Accepted);
        $completed = $challenges->firstWhere('status', ChallengeStatus::Completed);

        $this->assertCount(3, $challenges->pluck('template_key')->unique());
        $this->assertGreaterThan(0.0, (float) $accepted->current_value);
        $this->assertLessThan((float) $accepted->target_value, (float) $accepted->current_value);
        $this->assertGreaterThanOrEqual((float) $completed->target_value, (float) $completed->current_value);
    }
}
