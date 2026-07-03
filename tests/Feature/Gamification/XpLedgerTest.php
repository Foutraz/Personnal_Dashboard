<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\XpLedger;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class XpLedgerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_totals_xp_per_domain_for_a_user(): void
    {
        $user = User::factory()->create();
        XpEntry::factory()->count(2)->create(['user_id' => $user->id, 'domain' => GamificationDomain::Sport, 'points' => 10]);
        XpEntry::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Todo, 'points' => 3]);
        XpEntry::factory()->create(['points' => 99]);

        $totals = $this->app->make(XpLedger::class)->totalsByDomain($user);

        $this->assertSame(20, $totals[GamificationDomain::Sport->value]);
        $this->assertSame(3, $totals[GamificationDomain::Todo->value]);
        $this->assertArrayNotHasKey(GamificationDomain::Moto->value, $totals);
    }

    #[Test]
    public function it_sums_the_xp_gained_since_a_date(): void
    {
        $user = User::factory()->create();
        XpEntry::factory()->create(['user_id' => $user->id, 'points' => 10, 'occurred_at' => now()->subDays(2)]);
        XpEntry::factory()->create(['user_id' => $user->id, 'points' => 7, 'occurred_at' => now()->subDays(40)]);

        $gained = $this->app->make(XpLedger::class)->gainedSince($user, now()->subDays(30));

        $this->assertSame(10, $gained);
    }

    #[Test]
    public function it_builds_a_gapless_daily_series_for_the_last_days(): void
    {
        $user = User::factory()->create();
        XpEntry::factory()->create(['user_id' => $user->id, 'points' => 10, 'occurred_at' => now()->subDays(2)]);
        XpEntry::factory()->create(['user_id' => $user->id, 'points' => 5, 'occurred_at' => now()->subDays(2)]);

        $series = $this->app->make(XpLedger::class)->dailySeries($user, 7);

        $this->assertCount(7, $series);
        $this->assertSame(15, $series[now()->subDays(2)->toDateString()]);
        $this->assertSame(0, $series[now()->toDateString()]);
    }
}
