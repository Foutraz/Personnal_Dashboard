<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\RunUserGamification;
use Functional\Gamification\Actions\UpdateStreaks;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunUserGamificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-07-10 12:00', 'Europe/Paris')->utc());
    }

    #[Test]
    public function it_includes_the_milestone_points_in_the_refreshed_profile(): void
    {
        $user = User::factory()->create();
        $this->activitiesOver($user, 7);

        $this->app->make(RunUserGamification::class)->handle($user);

        $expected = 7 * 60 + config('gamification.streaks.milestones.7');
        $this->assertSame($expected, PlayerProfile::query()->whereBelongsTo($user)->sole()->total_xp);
    }

    #[Test]
    public function it_records_the_level_up_timestamp_when_the_level_changes(): void
    {
        $user = User::factory()->create();
        $this->activitiesOver($user, 8);

        $transition = $this->app->make(RunUserGamification::class)->handle($user);

        $profile = PlayerProfile::query()->whereBelongsTo($user)->sole();
        $this->assertSame(1, $transition->previousLevel);
        $this->assertSame($profile->level, $transition->currentLevel);
        $this->assertTrue($transition->leveledUp());
        $this->assertNotNull($profile->level_reached_at);
    }

    #[Test]
    public function it_keeps_the_milestone_and_the_level_timestamp_across_a_windowed_rerun(): void
    {
        $user = User::factory()->create();
        $this->activitiesOver($user, 8);
        $run = $this->app->make(RunUserGamification::class);
        $run->handle($user);
        $profile = PlayerProfile::query()->whereBelongsTo($user)->sole();
        $milestone = XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::StreakMilestone->value)->sole();

        $this->travel(1)->hours();
        $transition = $run->handle($user, now()->subDays(3));

        $this->assertFalse($transition->leveledUp());
        $this->assertTrue(XpEntry::query()->whereKey($milestone->id)->exists());
        $reloaded = PlayerProfile::query()->whereBelongsTo($user)->sole();
        $this->assertSame($profile->total_xp, $reloaded->total_xp);
        $this->assertTrue($profile->level_reached_at->equalTo($reloaded->level_reached_at));
    }

    #[Test]
    public function it_rolls_the_ledger_back_when_the_streak_update_fails(): void
    {
        $user = User::factory()->create();
        $this->activitiesOver($user, 3);
        $this->app->make(RunUserGamification::class)->handle($user);
        $ledgerIds = XpEntry::query()->whereBelongsTo($user)->pluck('id')->sort()->values()->all();

        $this->mock(UpdateStreaks::class)
            ->shouldReceive('handle')
            ->andThrow(new QueryException('sqlite', 'insert into streaks', [], new PDOException('no such table: streaks')));

        $this->assertThrows(
            fn () => $this->app->make(RunUserGamification::class)->handle($user, now()->subDays(7)),
            QueryException::class,
        );

        $this->assertSame($ledgerIds, XpEntry::query()->whereBelongsTo($user)->pluck('id')->sort()->values()->all());
    }

    private function activitiesOver(User $user, int $days): void
    {
        foreach (range(0, $days - 1) as $daysAgo) {
            SportActivity::factory()->create([
                'user_id' => $user->id,
                'distance' => 30000.0,
                'total_elevation_gain' => 2000.0,
                'started_at' => now()->subDays($daysAgo)->setTime(8, 0),
            ]);
        }
    }
}
