<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\AwardXp;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AwardXpTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_writes_awards_to_the_ledger_and_refreshes_the_profile(): void
    {
        $user = User::factory()->create();

        $this->app->make(AwardXp::class)->handle($user, collect([
            $this->award('sport_activity', 'activity-1', 40),
            $this->award('sport_activity', 'activity-2', 25),
        ]));

        $this->assertSame(2, XpEntry::query()->where('user_id', $user->id)->count());
        $profile = PlayerProfile::query()->where('user_id', $user->id)->sole();
        $this->assertSame(65, $profile->total_xp);
        $this->assertSame(1, $profile->level);
    }

    #[Test]
    public function it_silently_skips_awards_already_present_in_the_ledger(): void
    {
        $user = User::factory()->create();
        $action = $this->app->make(AwardXp::class);

        $action->handle($user, collect([$this->award('sport_activity', 'activity-1', 40)]));
        $action->handle($user, collect([
            $this->award('sport_activity', 'activity-1', 40),
            $this->award('sport_activity', 'activity-3', 12),
        ]));

        $this->assertSame(2, XpEntry::query()->where('user_id', $user->id)->count());
        $this->assertSame(52, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);
    }

    #[Test]
    public function it_records_the_level_up_timestamp_when_the_level_changes(): void
    {
        $user = User::factory()->create();

        $transition = $this->app->make(AwardXp::class)->handle($user, collect([
            $this->award('sport_activity', 'activity-1', 300),
        ]));

        $this->assertSame(1, $transition->previousLevel);
        $this->assertSame(2, $transition->currentLevel);
        $this->assertTrue($transition->leveledUp());
        $this->assertNotNull(PlayerProfile::query()->where('user_id', $user->id)->sole()->level_reached_at);
    }

    /**
     * Build a sport award targeting the given source with the given points.
     */
    private function award(string $ruleKey, string $sourceId, int $points): XpAward
    {
        return new XpAward(
            domain: GamificationDomain::Sport,
            ruleKey: $ruleKey,
            sourceType: 'test-source',
            sourceId: $sourceId,
            points: $points,
            occurredAt: now()->subDay(),
        );
    }
}
