<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\UpdateStreaks;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Enums\XpSourceType;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateStreaksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-07-10 12:00', 'Europe/Paris')->utc());
    }

    #[Test]
    public function it_builds_a_streak_from_consecutive_active_days(): void
    {
        $user = User::factory()->create();
        $this->entryOn($user, GamificationDomain::Sport, 2);
        $this->entryOn($user, GamificationDomain::Sport, 1);
        $this->entryOn($user, GamificationDomain::Sport, 0);

        $this->app->make(UpdateStreaks::class)->handle($user);

        $streak = Streak::query()->where('user_id', $user->id)->sole();
        $this->assertSame(GamificationDomain::Sport, $streak->domain);
        $this->assertSame(3, $streak->current_count);
        $this->assertSame(3, $streak->best_count);
        $this->assertSame(now()->toDateString(), $streak->last_activity_date->toDateString());
    }

    #[Test]
    public function it_counts_a_single_day_per_domain_regardless_of_entries(): void
    {
        $user = User::factory()->create();
        $this->entryOn($user, GamificationDomain::Todo, 0);
        $this->entryOn($user, GamificationDomain::Todo, 0);

        $this->app->make(UpdateStreaks::class)->handle($user);

        $this->assertSame(1, Streak::query()->where('user_id', $user->id)->sole()->current_count);
    }

    #[Test]
    public function it_keeps_a_streak_alive_when_the_last_activity_was_yesterday(): void
    {
        $user = User::factory()->create();
        $this->entryOn($user, GamificationDomain::Sport, 2);
        $this->entryOn($user, GamificationDomain::Sport, 1);

        $this->app->make(UpdateStreaks::class)->handle($user);

        $this->assertSame(2, Streak::query()->where('user_id', $user->id)->sole()->current_count);
    }

    #[Test]
    public function it_resets_the_current_count_when_the_streak_is_broken(): void
    {
        $user = User::factory()->create();
        $this->entryOn($user, GamificationDomain::Sport, 10);
        $this->entryOn($user, GamificationDomain::Sport, 9);
        $this->entryOn($user, GamificationDomain::Sport, 8);

        $this->app->make(UpdateStreaks::class)->handle($user);

        $streak = Streak::query()->where('user_id', $user->id)->sole();
        $this->assertSame(0, $streak->current_count);
        $this->assertSame(3, $streak->best_count);
    }

    #[Test]
    public function it_tracks_each_domain_independently(): void
    {
        $user = User::factory()->create();
        $this->entryOn($user, GamificationDomain::Sport, 1);
        $this->entryOn($user, GamificationDomain::Sport, 0);
        $this->entryOn($user, GamificationDomain::Todo, 0);

        $this->app->make(UpdateStreaks::class)->handle($user);

        $this->assertSame(2, Streak::query()->where('user_id', $user->id)->count());
        $sport = Streak::query()->where('user_id', $user->id)->where('domain', GamificationDomain::Sport->value)->sole();
        $this->assertSame(2, $sport->current_count);
    }

    #[Test]
    public function it_removes_the_projection_when_the_domain_has_no_active_days_left(): void
    {
        $user = User::factory()->create();
        Streak::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Moto]);

        $this->app->make(UpdateStreaks::class)->handle($user);

        $this->assertSame(0, Streak::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_is_idempotent_across_repeated_runs(): void
    {
        $user = User::factory()->create();
        $this->entryOn($user, GamificationDomain::Sport, 1);
        $this->entryOn($user, GamificationDomain::Sport, 0);
        $action = $this->app->make(UpdateStreaks::class);

        $action->handle($user);
        $action->handle($user);

        $this->assertSame(1, Streak::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, Streak::query()->where('user_id', $user->id)->sole()->current_count);
    }

    #[Test]
    public function it_awards_the_milestone_xp_when_a_run_reaches_seven_days(): void
    {
        $user = User::factory()->create();
        foreach (range(0, 6) as $daysAgo) {
            $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
        }

        $this->app->make(UpdateStreaks::class)->handle($user);

        $milestone = XpEntry::query()
            ->where('user_id', $user->id)
            ->where('rule_key', XpRuleKey::StreakMilestone->value)
            ->sole();
        $this->assertSame(config('gamification.streaks.milestones.7'), $milestone->points);
        $this->assertSame(GamificationDomain::Sport, $milestone->domain);
        $this->assertSame(XpSourceType::StreakMilestone->value, $milestone->source_type);
        $this->assertSame(now()->toDateString(), $milestone->occurred_at->toDateString());
    }

    #[Test]
    public function it_does_not_duplicate_milestones_across_runs(): void
    {
        $user = User::factory()->create();
        foreach (range(0, 6) as $daysAgo) {
            $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
        }
        $action = $this->app->make(UpdateStreaks::class);

        $action->handle($user);
        $action->handle($user);

        $this->assertSame(1, XpEntry::query()->where('rule_key', XpRuleKey::StreakMilestone->value)->count());
    }

    #[Test]
    public function it_removes_the_milestone_when_the_run_no_longer_reaches_it(): void
    {
        $user = User::factory()->create();
        foreach (range(0, 6) as $daysAgo) {
            $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
        }
        $action = $this->app->make(UpdateStreaks::class);
        $action->handle($user);

        XpEntry::query()
            ->where('user_id', $user->id)
            ->where('rule_key', '!=', XpRuleKey::StreakMilestone->value)
            ->where('occurred_at', '>=', now()->subDays(3)->startOfDay())
            ->delete();
        $action->handle($user);

        $this->assertSame(0, XpEntry::query()->where('rule_key', XpRuleKey::StreakMilestone->value)->count());
    }

    #[Test]
    public function it_ignores_milestone_entries_when_computing_active_days(): void
    {
        $user = User::factory()->create();
        foreach (range(0, 6) as $daysAgo) {
            $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
        }
        $action = $this->app->make(UpdateStreaks::class);
        $action->handle($user);

        $action->handle($user);

        $this->assertSame(7, Streak::query()->where('user_id', $user->id)->sole()->current_count);
    }

    #[Test]
    public function it_leaves_the_profile_refresh_to_the_gamification_run(): void
    {
        $user = User::factory()->create();
        foreach (range(0, 6) as $daysAgo) {
            $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
        }

        $this->app->make(UpdateStreaks::class)->handle($user);

        $this->assertSame(0, PlayerProfile::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_keeps_the_best_count_from_an_older_longer_run(): void
    {
        $user = User::factory()->create();
        foreach ([10, 9, 8, 7, 6] as $daysAgo) {
            $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
        }
        foreach ([1, 0] as $daysAgo) {
            $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
        }

        $this->app->make(UpdateStreaks::class)->handle($user);

        $streak = Streak::query()->where('user_id', $user->id)->sole();
        $this->assertSame(2, $streak->current_count);
        $this->assertSame(5, $streak->best_count);
    }

    #[Test]
    public function it_removes_a_milestone_that_would_only_survive_through_its_own_entry(): void
    {
        $user = User::factory()->create();
        foreach (range(0, 7) as $daysAgo) {
            $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
        }
        $action = $this->app->make(UpdateStreaks::class);
        $action->handle($user);

        XpEntry::query()
            ->where('user_id', $user->id)
            ->where('rule_key', '!=', XpRuleKey::StreakMilestone->value)
            ->whereDate('occurred_at', now()->subDay()->toDateString())
            ->delete();
        $action->handle($user);

        $this->assertSame(0, XpEntry::query()->where('user_id', $user->id)->where('rule_key', XpRuleKey::StreakMilestone->value)->count());
        $streak = Streak::query()->where('user_id', $user->id)->sole();
        $this->assertSame(1, $streak->current_count);
        $this->assertSame(6, $streak->best_count);
    }

    #[Test]
    public function it_awards_each_threshold_once_per_domain_even_after_a_break(): void
    {
        $user = User::factory()->create();
        foreach ([...range(20, 14), ...range(6, 0)] as $daysAgo) {
            $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
        }

        $this->app->make(UpdateStreaks::class)->handle($user);

        $milestone = $this->milestones($user)->sole();
        $this->assertSame('sport:7', $milestone->source_id);
        $this->assertSame(now()->subDays(14)->toDateString(), $milestone->occurred_at->toDateString());
    }

    #[Test]
    public function it_keeps_the_milestone_when_a_late_sync_merges_two_runs(): void
    {
        $user = User::factory()->create();
        foreach ([...range(14, 8), ...range(6, 0)] as $daysAgo) {
            $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
        }
        $action = $this->app->make(UpdateStreaks::class);
        $action->handle($user);
        $milestone = $this->milestones($user)->sole();

        $this->entryOn($user, GamificationDomain::Sport, 7);
        $action->handle($user);

        $merged = $this->milestones($user)->sole();
        $this->assertSame($milestone->id, $merged->id);
        $this->assertSame($milestone->occurred_at->toDateString(), $merged->occurred_at->toDateString());
        $this->assertSame(15, Streak::query()->whereBelongsTo($user)->sole()->current_count);
    }

    #[Test]
    public function it_awards_the_same_threshold_separately_for_each_domain(): void
    {
        $user = User::factory()->create();
        foreach (range(6, 0) as $daysAgo) {
            $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
            $this->entryOn($user, GamificationDomain::Todo, $daysAgo);
        }

        $this->app->make(UpdateStreaks::class)->handle($user);

        $this->assertSame(['sport:7', 'todo:7'], $this->milestones($user)->pluck('source_id')->sort()->values()->all());
    }

    #[Test]
    public function it_buckets_an_activity_at_half_past_one_in_paris_on_the_paris_day(): void
    {
        $user = User::factory()->create();
        $this->entryAt($user, '2026-07-09 12:00');
        $this->entryAt($user, '2026-07-10 01:30');

        $this->app->make(UpdateStreaks::class)->handle($user);

        $streak = Streak::query()->whereBelongsTo($user)->sole();
        $this->assertSame(2, $streak->current_count);
        $this->assertSame('2026-07-10', $streak->last_activity_date->toDateString());
    }

    #[Test]
    public function it_breaks_the_streak_once_the_paris_day_after_yesterday_has_started(): void
    {
        $this->travelTo(Carbon::parse('2026-07-11 00:30', 'Europe/Paris')->utc());
        $user = User::factory()->create();
        $this->entryAt($user, '2026-07-08 18:00');
        $this->entryAt($user, '2026-07-09 18:00');

        $this->app->make(UpdateStreaks::class)->handle($user);

        $streak = Streak::query()->whereBelongsTo($user)->sole();
        $this->assertSame(0, $streak->current_count);
        $this->assertSame(2, $streak->best_count);
    }

    #[Test]
    public function it_follows_the_configured_gamification_timezone(): void
    {
        config(['gamification.timezone' => 'UTC']);
        $user = User::factory()->create();
        $this->entryAt($user, '2026-07-09 12:00');
        $this->entryAt($user, '2026-07-10 01:30');

        $this->app->make(UpdateStreaks::class)->handle($user);

        $streak = Streak::query()->whereBelongsTo($user)->sole();
        $this->assertSame(1, $streak->current_count);
        $this->assertSame('2026-07-09', $streak->last_activity_date->toDateString());
    }

    private function entryAt(User $user, string $parisTime): XpEntry
    {
        return XpEntry::factory()->create([
            'user_id' => $user->id,
            'domain' => GamificationDomain::Sport,
            'points' => 10,
            'occurred_at' => Carbon::parse($parisTime, 'Europe/Paris')->utc(),
        ]);
    }

    /**
     * @return Collection<int, XpEntry>
     */
    private function milestones(User $user): Collection
    {
        return XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::StreakMilestone->value)->get();
    }

    /**
     * Create a ledger entry for the domain the given number of days ago.
     */
    private function entryOn(User $user, GamificationDomain $domain, int $daysAgo): XpEntry
    {
        return XpEntry::factory()->create([
            'user_id' => $user->id,
            'domain' => $domain,
            'points' => 10,
            'occurred_at' => now()->subDays($daysAgo),
        ]);
    }
}
