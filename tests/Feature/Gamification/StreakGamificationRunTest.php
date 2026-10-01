<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StreakGamificationRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-07-10 12:00', 'Europe/Paris')->utc());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function productionWindows(): array
    {
        return [
            'daily schedule' => [3],
            'sync listener' => [7],
        ];
    }

    #[Test]
    #[DataProvider('productionWindows')]
    public function it_keeps_the_milestone_intact_through_a_windowed_job_run(int $windowDays): void
    {
        $user = User::factory()->create();
        $this->activitiesOn($user, range(7, 0));
        ProcessUserGamificationJob::dispatchSync($user->id);
        $milestone = $this->milestones($user)->sole();
        $profile = PlayerProfile::query()->whereBelongsTo($user)->sole();

        $this->travel(2)->hours();
        ProcessUserGamificationJob::dispatchSync($user->id, now()->subDays($windowDays));

        $kept = $this->milestones($user)->sole();
        $this->assertSame($milestone->id, $kept->id);
        $this->assertSame($milestone->points, $kept->points);
        $reloaded = PlayerProfile::query()->whereBelongsTo($user)->sole();
        $this->assertSame($profile->total_xp, $reloaded->total_xp);
        $this->assertSame((int) XpEntry::query()->whereBelongsTo($user)->sum('points'), $reloaded->total_xp);
        $this->assertNotNull($profile->level_reached_at);
        $this->assertTrue($profile->level_reached_at->equalTo($reloaded->level_reached_at));
    }

    #[Test]
    public function it_does_not_award_the_milestone_again_after_a_break(): void
    {
        $user = User::factory()->create();
        $this->activitiesOn($user, [...range(20, 14), ...range(6, 0)]);

        ProcessUserGamificationJob::dispatchSync($user->id);
        ProcessUserGamificationJob::dispatchSync($user->id, now()->subDays(7));

        $this->assertSame(['sport:7'], $this->milestones($user)->pluck('source_id')->all());
        $distanceCountAndStreakBadges = 3 * BadgeTier::Bronze->xpReward();
        $expected = 14 * 60 + config('gamification.streaks.milestones.7') + $distanceCountAndStreakBadges;
        $this->assertSame($expected, PlayerProfile::query()->whereBelongsTo($user)->sole()->total_xp);
    }

    #[Test]
    public function it_drops_the_current_count_but_keeps_the_best_when_the_last_activity_is_two_days_old(): void
    {
        $user = User::factory()->create();
        $this->activitiesOn($user, range(6, 2));

        ProcessUserGamificationJob::dispatchSync($user->id);

        $streak = Streak::query()->whereBelongsTo($user)->sole();
        $this->assertSame(GamificationDomain::Sport, $streak->domain);
        $this->assertSame(0, $streak->current_count);
        $this->assertSame(5, $streak->best_count);
        $this->assertSame(now()->subDays(2)->toDateString(), $streak->last_activity_date->toDateString());
    }

    /**
     * @param  array<int, int>  $daysAgo
     */
    private function activitiesOn(User $user, array $daysAgo): void
    {
        foreach ($daysAgo as $offset) {
            SportActivity::factory()->create([
                'user_id' => $user->id,
                'distance' => 30000.0,
                'total_elevation_gain' => 2000.0,
                'started_at' => now()->subDays($offset)->setTime(7, 0),
            ]);
        }
    }

    /**
     * @return Collection<int, XpEntry>
     */
    private function milestones(User $user): Collection
    {
        return XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::StreakMilestone->value)->get();
    }
}
