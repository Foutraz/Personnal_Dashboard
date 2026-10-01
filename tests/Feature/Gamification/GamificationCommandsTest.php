<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Notifications\BadgeAwardedNotification;
use Functional\Sport\Models\SportActivity;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
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

    #[Test]
    public function it_drops_the_awards_of_a_deleted_source_on_the_next_recalculation(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 10000.0, 'total_elevation_gain' => 100.0]);
        $task = Task::factory()->completed()->create(['user_id' => $user->id, 'completed_at' => now()->subDay()]);

        $this->artisan('gamification:recalculate', ['user' => $user->id])->assertExitCode(0);

        $this->assertSame(24, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);

        $task->delete();

        $this->artisan('gamification:recalculate', ['user' => $user->id])->assertExitCode(0);

        $this->assertSame(0, XpEntry::query()->where('user_id', $user->id)->where('rule_key', 'todo_task_completed')->count());
        $this->assertSame(21, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);
    }

    #[Test]
    public function it_keeps_the_awarded_badge_and_the_xp_total_when_recalculating_after_the_job(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->count(10)->create(['user_id' => $user->id, 'distance' => 1000.0]);
        ProcessUserGamificationJob::dispatchSync($user->id);
        $award = BadgeAward::query()->whereBelongsTo($user)->sole();
        $totalXp = PlayerProfile::query()->whereBelongsTo($user)->sole()->total_xp;
        $badgeEntry = XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::BadgeAward->value)->sole();
        Notification::fake();

        $this->artisan('gamification:recalculate', ['user' => $user->id])->assertExitCode(0);

        $recalculatedAward = BadgeAward::query()->whereBelongsTo($user)->sole();
        $recalculatedEntry = XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::BadgeAward->value)->sole();
        $this->assertSame($award->id, $recalculatedAward->id);
        $this->assertTrue($award->awarded_at->equalTo($recalculatedAward->awarded_at));
        $this->assertSame($badgeEntry->source_id, $recalculatedEntry->source_id);
        $this->assertSame($badgeEntry->points, $recalculatedEntry->points);
        $this->assertTrue($badgeEntry->occurred_at->equalTo($recalculatedEntry->occurred_at));
        $this->assertSame($totalXp, PlayerProfile::query()->whereBelongsTo($user)->sole()->total_xp);
        Notification::assertNothingSent();
    }

    #[Test]
    public function it_stores_the_badge_notification_when_the_recalculation_awards_a_new_badge(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->count(10)->create(['user_id' => $user->id, 'distance' => 1000.0]);

        $this->artisan('gamification:recalculate', ['user' => $user->id])->assertExitCode(0);

        $this->assertSame(BadgeAward::query()->whereBelongsTo($user)->count(), $user->notifications()->where('type', BadgeAwardedNotification::class)->count());
        $this->assertGreaterThan(0, BadgeAward::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_skips_a_user_whose_gamification_lock_is_already_held(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 10000.0, 'total_elevation_gain' => 100.0]);
        $stale = XpEntry::factory()->create(['user_id' => $user->id, 'rule_key' => 'stale_rule', 'points' => 500]);

        $lock = Cache::lock(ProcessUserGamificationJob::overlapKey($user->id), 30);
        $this->assertTrue($lock->get());

        $this->artisan('gamification:recalculate', ['user' => $user->id])->assertExitCode(0);

        $lock->release();

        $this->assertSame(1, XpEntry::query()->where('user_id', $user->id)->where('id', $stale->id)->count());
        $this->assertSame(1, XpEntry::query()->where('user_id', $user->id)->count());
    }
}
