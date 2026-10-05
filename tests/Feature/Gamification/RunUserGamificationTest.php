<?php

namespace Tests\Feature\Gamification;

use Functional\Exploration\Models\ExploredCell;
use Functional\Gamification\Actions\AwardXp;
use Functional\Gamification\Actions\EvaluateBadges;
use Functional\Gamification\Actions\RefreshPlayerProfile;
use Functional\Gamification\Actions\RunChallengeCycle;
use Functional\Gamification\Actions\RunUserGamification;
use Functional\Gamification\Actions\SyncBadgeCatalogue;
use Functional\Gamification\Actions\TransitionChallenge;
use Functional\Gamification\Actions\UpdateStreaks;
use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Notifications\BadgeAwardedNotification;
use Functional\Gamification\Notifications\ChallengeCompletedNotification;
use Functional\Gamification\Notifications\ChallengesProposedNotification;
use Functional\Gamification\Services\Dto\ChallengeCycleOutcome;
use Functional\Gamification\Services\Dto\LevelTransition;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Moto\Models\MotoRide;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
    public function it_notifies_the_proposed_challenge_set_once(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        $this->sportHistory($user);
        $this->motoHistory($user);
        $this->explorationHistory($user);
        $run = $this->app->make(RunUserGamification::class);

        $run->handle($user);
        $run->handle($user);

        $this->assertSame(3, Challenge::query()->whereBelongsTo($user)->count());
        $notification = $user->notifications()->where('type', ChallengesProposedNotification::class)->get()->sole();
        $this->assertSame('3 nouveaux défis cette semaine', $notification->data['title']);
        $this->assertSame('2026-W28', $notification->data['week_key']);
        $this->assertSame(3, $notification->data['count']);
    }

    #[Test]
    public function it_announces_a_single_proposed_challenge_in_the_singular(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        $this->sportHistory($user);

        $this->app->make(RunUserGamification::class)->handle($user);

        $notification = $user->notifications()->where('type', ChallengesProposedNotification::class)->get()->sole();
        $this->assertSame('1 nouveau défi cette semaine', $notification->data['title']);
    }

    #[Test]
    public function it_sends_no_challenge_notification_to_a_user_without_history(): void
    {
        $user = User::factory()->create();

        $this->app->make(RunUserGamification::class)->handle($user);

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(0, Challenge::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_notifies_each_completed_challenge_once(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        $this->sportHistory($user);
        $run = $this->app->make(RunUserGamification::class);
        $run->handle($user);
        $challenge = $this->acceptedProposal($user);
        $this->reachTheTarget($user, $challenge);

        $run->handle($user);
        $run->handle($user);

        $notification = $user->notifications()->where('type', ChallengeCompletedNotification::class)->get()->sole();
        $this->assertSame('Défi réussi : Distance sportive', $notification->data['title']);
        $this->assertSame(sprintf('Vous avez atteint %s km et gagné 50 XP.', (int) $challenge->target_value), $notification->data['body']);
        $this->assertSame($challenge->id, $notification->data['challenge_id']);
        $this->assertSame(ChallengeStatus::Completed, $challenge->fresh()->status);
    }

    #[Test]
    public function it_includes_the_challenge_xp_in_the_refreshed_profile_in_the_same_pass(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->accepted()->create(['user_id' => $user->id, 'target_value' => 28]);
        $this->reachTheTarget($user, $challenge);

        $this->app->make(RunUserGamification::class)->handle($user);

        $this->assertSame(50, (int) XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::ChallengeCompleted->value)->sum('points'));
        $this->assertSame(
            (int) XpEntry::query()->whereBelongsTo($user)->sum('points'),
            PlayerProfile::query()->whereBelongsTo($user)->sole()->total_xp,
        );
    }

    #[Test]
    public function it_keeps_the_challenge_xp_through_a_full_pass_after_the_activity_disappears(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->accepted()->create(['user_id' => $user->id, 'target_value' => 28]);
        $this->reachTheTarget($user, $challenge);
        $run = $this->app->make(RunUserGamification::class);
        $run->handle($user);
        SportActivity::query()->whereBelongsTo($user)->delete();

        $run->handle($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Completed, $persisted->status);
        $this->assertSame(0, XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::SportActivity->value)->count());
        $entry = XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::ChallengeCompleted->value)->sole();
        $this->assertSame(50, $entry->points);
        $this->assertSame($challenge->id, $entry->source_id);
        $this->assertSame(
            (int) XpEntry::query()->whereBelongsTo($user)->sum('points'),
            PlayerProfile::query()->whereBelongsTo($user)->sole()->total_xp,
        );
    }

    #[Test]
    public function it_stores_no_challenge_xp_or_notification_when_a_later_step_fails(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        $this->mock(RefreshPlayerProfile::class)
            ->shouldReceive('handle')
            ->andThrow(new QueryException('sqlite', 'insert into player_profiles', [], new PDOException('no such table: player_profiles')));

        $this->assertThrows(
            fn () => $this->app->make(RunUserGamification::class)->handle($user),
            QueryException::class,
        );

        $this->assertSame(0, Challenge::query()->whereBelongsTo($user)->count());
        $this->assertSame(0, XpEntry::query()->whereBelongsTo($user)->count());
        $this->assertSame(0, $user->notifications()->count());
    }

    #[Test]
    public function it_keeps_a_challenge_open_and_unrewarded_when_a_later_step_fails(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->accepted()->create(['user_id' => $user->id, 'target_value' => 28]);
        $this->reachTheTarget($user, $challenge);
        $this->mock(RefreshPlayerProfile::class)
            ->shouldReceive('handle')
            ->andThrow(new QueryException('sqlite', 'insert into player_profiles', [], new PDOException('no such table: player_profiles')));

        $this->assertThrows(
            fn () => $this->app->make(RunUserGamification::class)->handle($user),
            QueryException::class,
        );

        $this->assertSame(ChallengeStatus::Accepted, $challenge->fresh()->status);
        $this->assertSame(0, XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::ChallengeCompleted->value)->count());
        $this->assertSame(0, $user->notifications()->count());
    }

    #[Test]
    public function it_proposes_and_notifies_again_when_the_run_is_retried_after_a_challenge_notification_failure(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        Event::listen(NotificationSending::class, function (): void {
            throw new QueryException('sqlite', 'insert into notifications', [], new PDOException('no such table: notifications'));
        });
        $run = $this->app->make(RunUserGamification::class);
        $this->assertThrows(fn () => $run->handle($user), QueryException::class);
        $this->assertSame(0, Challenge::query()->whereBelongsTo($user)->count());
        Event::forget(NotificationSending::class);

        $run->handle($user);

        $this->assertSame(1, Challenge::query()->whereBelongsTo($user)->count());
        $this->assertSame(1, $user->notifications()->where('type', ChallengesProposedNotification::class)->count());
    }

    #[Test]
    public function it_has_the_challenge_rows_in_place_when_the_profile_is_refreshed(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        $profileChallengeCounts = [];
        $this->mock(RefreshPlayerProfile::class)
            ->shouldReceive('handle')
            ->andReturnUsing(function (User $refreshed) use (&$profileChallengeCounts): LevelTransition {
                $profileChallengeCounts[] = Challenge::query()->whereBelongsTo($refreshed)->count();

                return new LevelTransition(1, 1);
            });

        $this->app->make(RunUserGamification::class)->handle($user);

        $this->assertSame([1], $profileChallengeCounts);
    }

    #[Test]
    public function it_runs_the_challenge_cycle_after_the_badges_and_before_the_profile_refresh(): void
    {
        $user = User::factory()->create();
        $order = [];
        $this->mock(AwardXp::class)->shouldReceive('handle')->andReturnUsing(function () use (&$order): void {
            $order[] = 'xp';
        });
        $this->mock(UpdateStreaks::class)->shouldReceive('handle')->andReturnUsing(function () use (&$order): void {
            $order[] = 'streaks';
        });
        $this->mock(EvaluateBadges::class)->shouldReceive('handle')->andReturnUsing(function () use (&$order): EloquentCollection {
            $order[] = 'badges';

            return new EloquentCollection;
        });
        $this->mock(RunChallengeCycle::class)->shouldReceive('handle')->andReturnUsing(function () use (&$order): ChallengeCycleOutcome {
            $order[] = 'challenges';

            return new ChallengeCycleOutcome($this->app->make(GamificationCalendar::class)->currentWeek(), collect(), collect());
        });
        $this->mock(RefreshPlayerProfile::class)->shouldReceive('handle')->andReturnUsing(function () use (&$order): LevelTransition {
            $order[] = 'profile';

            return new LevelTransition(1, 1);
        });

        $this->app->make(RunUserGamification::class)->handle($user);

        $this->assertSame(['xp', 'streaks', 'badges', 'challenges', 'profile'], $order);
    }

    #[Test]
    public function it_leaves_the_badge_catalogue_to_its_callers(): void
    {
        $this->mock(SyncBadgeCatalogue::class)->shouldNotReceive('handle');
        $user = User::factory()->create();

        $this->app->make(RunUserGamification::class)->handle($user);

        $this->assertTrue(PlayerProfile::query()->whereBelongsTo($user)->exists());
    }

    private function sportHistory(User $user): void
    {
        foreach (['2026-06-17' => 10000.0, '2026-06-24' => 20000.0, '2026-07-01' => 30000.0] as $day => $distance) {
            SportActivity::factory()->create([
                'user_id' => $user->id,
                'distance' => $distance,
                'moving_time' => 0,
                'total_elevation_gain' => 0,
                'started_at' => Carbon::parse("{$day} 12:00:00", 'UTC'),
            ]);
        }
    }

    private function motoHistory(User $user): void
    {
        foreach (['2026-06-17' => 100.0, '2026-06-24' => 120.0, '2026-07-01' => 140.0] as $day => $kilometers) {
            MotoRide::factory()->create([
                'user_id' => $user->id,
                'distance' => $kilometers,
                'started_at' => Carbon::parse("{$day} 12:00:00", 'UTC'),
            ]);
        }
    }

    private function explorationHistory(User $user): void
    {
        foreach (['2026-06-17', '2026-06-24', '2026-07-01'] as $day) {
            ExploredCell::factory()->create([
                'user_id' => $user->id,
                'cell_key' => "cell:{$day}",
                'first_seen_at' => Carbon::parse("{$day} 12:00:00", 'UTC'),
            ]);
        }
    }

    private function acceptedProposal(User $user): Challenge
    {
        $challenge = Challenge::query()->whereBelongsTo($user)->sole();
        $this->assertSame(ChallengeTemplateKey::SportDistance, $challenge->template_key);
        $this->app->make(TransitionChallenge::class)->handle($challenge, $challenge->state()->accept());

        return $challenge;
    }

    private function reachTheTarget(User $user, Challenge $challenge): void
    {
        SportActivity::factory()->create([
            'user_id' => $user->id,
            'distance' => ((float) $challenge->target_value + 2) * 1000,
            'moving_time' => 0,
            'total_elevation_gain' => 0,
            'started_at' => now()->subDays(2)->setTime(8, 0),
        ]);
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
