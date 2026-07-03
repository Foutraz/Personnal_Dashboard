<?php

namespace Tests\Feature\Gamification;

use Functional\Exploration\Events\CoverageRebuilt;
use Functional\Finance\Events\BankTransactionsSynced;
use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Functional\Health\Events\WithingsMeasurementsSynced;
use Functional\Sport\Events\StravaActivitiesSynced;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncEventWiringTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_processes_gamification_after_each_sync_event(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        event(new StravaActivitiesSynced($user->id));
        event(new WithingsMeasurementsSynced($user->id));
        event(new BankTransactionsSynced($user->id));
        event(new CoverageRebuilt($user->id));

        Queue::assertPushed(ProcessUserGamificationJob::class, 4);
        Queue::assertPushed(fn (ProcessUserGamificationJob $job): bool => $job->userId === $user->id && $job->since !== null);
    }

    #[Test]
    public function it_deletes_the_users_gamification_data_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create();
        XpEntry::factory()->create(['user_id' => $user->id]);
        PlayerProfile::factory()->create(['user_id' => $user->id]);

        $other = User::factory()->create();
        $otherEntry = XpEntry::factory()->create(['user_id' => $other->id]);

        $user->delete();

        $this->assertSame(0, XpEntry::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, PlayerProfile::query()->where('user_id', $user->id)->count());
        $this->assertTrue(XpEntry::query()->whereKey($otherEntry->id)->exists());
    }
}
