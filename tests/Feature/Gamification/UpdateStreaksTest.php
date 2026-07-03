<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\UpdateStreaks;
use Functional\Gamification\Enums\GamificationDomain;
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
