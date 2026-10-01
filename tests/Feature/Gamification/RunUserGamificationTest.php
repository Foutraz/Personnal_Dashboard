<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\RefreshPlayerProfile;
use Functional\Gamification\Actions\RunUserGamification;
use Functional\Gamification\Actions\SyncBadgeCatalogue;
use Functional\Gamification\Actions\UpdateStreaks;
use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Notifications\BadgeAwardedNotification;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
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
        $this->app->make(SyncBadgeCatalogue::class)->handle();
    }

    #[Test]
    public function it_includes_the_milestone_points_in_the_refreshed_profile(): void
    {
        $user = User::factory()->create();
        $this->activitiesOver($user, 7);

        $this->app->make(RunUserGamification::class)->handle($user);

        $distanceAndStreakBadges = 2 * BadgeTier::Bronze->xpReward();
        $expected = 7 * 60 + config('gamification.streaks.milestones.7') + $distanceAndStreakBadges;
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

    #[Test]
    public function it_notifies_each_new_badge_once(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->smallActivities($user, 10);
        $run = $this->app->make(RunUserGamification::class);

        $run->handle($user);
        $run->handle($user);

        Notification::assertSentToTimes($user, BadgeAwardedNotification::class, 1);
        Notification::assertSentTo(
            $user,
            BadgeAwardedNotification::class,
            fn (BadgeAwardedNotification $notification): bool => $notification->badge->key === BadgeRuleKey::SportActivityCount->badgeKey(BadgeTier::Bronze),
        );
    }

    #[Test]
    public function it_awards_the_streak_badge_from_the_projection_computed_in_the_same_pass(): void
    {
        $user = User::factory()->create();
        $this->activitiesOver($user, 7);

        $this->app->make(RunUserGamification::class)->handle($user);

        $this->assertTrue(
            BadgeAward::query()->whereBelongsTo($user)->whereHas('badge', fn ($query) => $query->where('key', BadgeRuleKey::SportStreak->badgeKey(BadgeTier::Bronze)))->exists(),
        );
    }

    #[Test]
    public function it_includes_the_badge_xp_in_the_refreshed_profile_in_the_same_pass(): void
    {
        $user = User::factory()->create();
        $this->smallActivities($user, 10);

        $this->app->make(RunUserGamification::class)->handle($user);

        $ledgerSum = (int) XpEntry::query()->whereBelongsTo($user)->sum('points');
        $this->assertSame(BadgeTier::Bronze->xpReward(), (int) XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::BadgeAward->value)->sum('points'));
        $this->assertSame($ledgerSum, PlayerProfile::query()->whereBelongsTo($user)->sole()->total_xp);
    }

    #[Test]
    public function it_awards_no_badge_and_stores_no_notification_when_a_later_step_fails(): void
    {
        $user = User::factory()->create();
        $this->smallActivities($user, 10);

        $this->mock(RefreshPlayerProfile::class)
            ->shouldReceive('handle')
            ->andThrow(new QueryException('sqlite', 'insert into player_profiles', [], new PDOException('no such table: player_profiles')));

        $this->assertThrows(
            fn () => $this->app->make(RunUserGamification::class)->handle($user),
            QueryException::class,
        );

        $this->assertSame(0, BadgeAward::query()->whereBelongsTo($user)->count());
        $this->assertSame(0, XpEntry::query()->whereBelongsTo($user)->count());
        $this->assertSame(0, $user->notifications()->count());
    }

    #[Test]
    public function it_rolls_the_awards_back_when_a_notification_fails(): void
    {
        $user = User::factory()->create();
        $this->smallActivities($user, 10);
        Event::listen(NotificationSending::class, function (): void {
            throw new QueryException('sqlite', 'insert into notifications', [], new PDOException('no such table: notifications'));
        });

        $this->assertThrows(
            fn () => $this->app->make(RunUserGamification::class)->handle($user),
            QueryException::class,
        );

        $this->assertSame(0, BadgeAward::query()->whereBelongsTo($user)->count());
        $this->assertSame(0, XpEntry::query()->whereBelongsTo($user)->count());
        $this->assertSame(0, $user->notifications()->count());
    }

    #[Test]
    public function it_awards_and_notifies_again_when_the_run_is_retried_after_a_notification_failure(): void
    {
        $user = User::factory()->create();
        $this->smallActivities($user, 10);
        Event::listen(NotificationSending::class, function (): void {
            throw new QueryException('sqlite', 'insert into notifications', [], new PDOException('no such table: notifications'));
        });
        $run = $this->app->make(RunUserGamification::class);
        $this->assertThrows(fn () => $run->handle($user), QueryException::class);
        Event::forget(NotificationSending::class);

        $run->handle($user);

        $this->assertSame(1, BadgeAward::query()->whereBelongsTo($user)->count());
        $this->assertSame(1, $user->notifications()->where('type', BadgeAwardedNotification::class)->count());
    }

    #[Test]
    public function it_leaves_the_badge_catalogue_to_its_callers(): void
    {
        $this->mock(SyncBadgeCatalogue::class)->shouldNotReceive('handle');
        $user = User::factory()->create();

        $this->app->make(RunUserGamification::class)->handle($user);

        $this->assertTrue(PlayerProfile::query()->whereBelongsTo($user)->exists());
    }

    private function smallActivities(User $user, int $count): void
    {
        SportActivity::factory()->count($count)->create([
            'user_id' => $user->id,
            'distance' => 1000.0,
            'started_at' => now()->subDays(2)->setTime(8, 0),
        ]);
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
