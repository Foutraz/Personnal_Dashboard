<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GamificationCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_backfills_every_user_over_the_full_history(): void
    {
        Queue::fake();
        $users = User::factory()->count(2)->create();

        $this->artisan('gamification:backfill')->assertExitCode(0);

        Queue::assertPushed(ProcessUserGamificationJob::class, 2);
        Queue::assertPushed(fn (ProcessUserGamificationJob $job): bool => $job->userId === $users->first()->id && $job->since === null);
    }

    #[Test]
    public function it_backfills_a_single_user_when_targeted(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        User::factory()->create();

        $this->artisan('gamification:backfill', ['user' => $user->id])->assertExitCode(0);

        Queue::assertPushed(ProcessUserGamificationJob::class, 1);
    }

    #[Test]
    public function it_recalculates_the_ledger_from_scratch(): void
    {
        $user = User::factory()->create();
        $activity = SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 10000.0, 'total_elevation_gain' => 100.0]);
        XpEntry::factory()->create(['user_id' => $user->id, 'rule_key' => 'sport_activity', 'source_type' => SportActivity::class, 'source_id' => $activity->id, 'points' => 999]);
        XpEntry::factory()->create(['user_id' => $user->id, 'rule_key' => 'stale_rule', 'points' => 500]);

        $this->artisan('gamification:recalculate', ['user' => $user->id])->assertExitCode(0);

        $entries = XpEntry::query()->where('user_id', $user->id)->get();
        $this->assertCount(1, $entries);
        $this->assertSame(21, $entries->first()->points);
        $this->assertSame(21, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);
    }
}
