<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\UpdateStreaks;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateStreaksTest extends TestCase
{
    use RefreshDatabase;

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
            ->where('rule_key', Streak::MILESTONE_RULE_KEY)
            ->sole();
        $this->assertSame(config('gamification.streaks.milestones.7'), $milestone->points);
        $this->assertSame(GamificationDomain::Sport, $milestone->domain);
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

        $this->assertSame(1, XpEntry::query()->where('rule_key', Streak::MILESTONE_RULE_KEY)->count());
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
            ->where('rule_key', '!=', Streak::MILESTONE_RULE_KEY)
            ->where('occurred_at', '>=', now()->subDays(3)->startOfDay())
            ->delete();
        $action->handle($user);

        $this->assertSame(0, XpEntry::query()->where('rule_key', Streak::MILESTONE_RULE_KEY)->count());
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
    public function it_refreshes_the_player_profile_with_the_milestone_points(): void
    {
        $user = User::factory()->create();
        foreach (range(0, 6) as $daysAgo) {
            $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
        }

        $this->app->make(UpdateStreaks::class)->handle($user);

        $expected = 7 * 10 + config('gamification.streaks.milestones.7');
        $this->assertSame($expected, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);
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
