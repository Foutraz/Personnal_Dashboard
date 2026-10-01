<?php

namespace Tests\Feature\Gamification;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\BankTransaction;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Sport\Models\SportActivity;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessUserGamificationJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_awards_xp_across_all_domains_and_stays_idempotent(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 10000.0, 'total_elevation_gain' => 100.0]);
        Task::factory()->completed()->create(['user_id' => $user->id, 'completed_at' => now()->subDay()]);

        ProcessUserGamificationJob::dispatchSync($user->id);
        ProcessUserGamificationJob::dispatchSync($user->id);

        $this->assertSame(2, XpEntry::query()->where('user_id', $user->id)->count());
        $profile = PlayerProfile::query()->where('user_id', $user->id)->sole();
        $this->assertSame(21 + 3, $profile->total_xp);
    }

    #[Test]
    public function it_stays_stable_across_repeated_full_replays(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 10000.0, 'total_elevation_gain' => 100.0]);
        Task::factory()->completed()->create(['user_id' => $user->id, 'completed_at' => now()->subDay()]);
        BankTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'booked_at' => now()->subMonth()->startOfMonth()->addDays(3)]);

        for ($run = 0; $run < 3; $run++) {
            ProcessUserGamificationJob::dispatchSync($user->id);
        }

        $this->assertSame(3, XpEntry::query()->where('user_id', $user->id)->count());
        $ledgerSum = (int) XpEntry::query()->where('user_id', $user->id)->sum('points');
        $this->assertSame(21 + 3 + 20, $ledgerSum);
        $this->assertSame($ledgerSum, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);
    }

    #[Test]
    public function it_only_processes_sources_inside_the_since_window(): void
    {
        $user = User::factory()->create();
        XpEntry::factory()->create(['user_id' => $user->id, 'rule_key' => 'seeded']);
        SportActivity::factory()->create(['user_id' => $user->id, 'started_at' => now()->subMonths(3)]);
        SportActivity::factory()->create(['user_id' => $user->id, 'started_at' => now()->subDay()]);

        ProcessUserGamificationJob::dispatchSync($user->id, now()->subDays(7));

        $this->assertSame(1, XpEntry::query()->where('user_id', $user->id)->where('rule_key', 'sport_activity')->count());
    }

    #[Test]
    public function it_falls_back_to_the_full_history_when_the_ledger_is_empty(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'started_at' => now()->subMonths(3)]);

        ProcessUserGamificationJob::dispatchSync($user->id, now()->subDays(7));

        $this->assertSame(1, XpEntry::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_updates_a_period_award_when_the_month_data_changes(): void
    {
        $user = User::factory()->create();
        $month = now()->subMonth()->startOfMonth();
        BankTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'booked_at' => $month->copy()->addDays(3)]);

        ProcessUserGamificationJob::dispatchSync($user->id);

        $position = Position::factory()->create(['user_id' => $user->id]);
        InvestmentTransaction::factory()->create(['position_id' => $position->id, 'type' => TransactionType::Buy, 'executed_at' => $month->copy()->addDays(5)]);

        ProcessUserGamificationJob::dispatchSync($user->id);

        $entry = XpEntry::query()->where('user_id', $user->id)->where('rule_key', 'finance_month')->sole();
        $this->assertSame(30, $entry->points);
        $this->assertSame(30, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);
    }

    #[Test]
    public function it_keeps_the_daily_todo_cap_when_an_earlier_task_reorders_the_day(): void
    {
        $user = User::factory()->create();
        $day = now()->subDays(2)->startOfDay();

        for ($hour = 1; $hour <= 10; $hour++) {
            Task::factory()->completed()->create(['user_id' => $user->id, 'completed_at' => $day->copy()->addHours($hour)]);
        }

        ProcessUserGamificationJob::dispatchSync($user->id);

        $this->assertSame(30, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);

        Task::factory()->completed()->create(['user_id' => $user->id, 'completed_at' => $day->copy()->addMinutes(1)]);

        ProcessUserGamificationJob::dispatchSync($user->id);

        $this->assertSame(10, XpEntry::query()->where('user_id', $user->id)->where('rule_key', 'todo_task_completed')->count());
        $this->assertSame(30, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);
    }

    #[Test]
    public function it_removes_a_finance_month_award_when_the_month_falls_below_the_threshold(): void
    {
        $user = User::factory()->create();
        $month = now()->subMonth()->startOfMonth();
        BankTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'booked_at' => $month->copy()->addDays(3)]);

        ProcessUserGamificationJob::dispatchSync($user->id);

        $this->assertSame(20, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);

        BankTransaction::factory()->create(['user_id' => $user->id, 'amount' => -1500, 'booked_at' => $month->copy()->addDays(6)]);

        ProcessUserGamificationJob::dispatchSync($user->id);

        $this->assertSame(0, XpEntry::query()->where('user_id', $user->id)->where('rule_key', 'finance_month')->count());
        $this->assertSame(0, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);
    }

    #[Test]
    public function it_lowers_the_total_when_a_source_disappears_on_a_full_recalculation(): void
    {
        $user = User::factory()->create();
        $activity = SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 10000.0, 'total_elevation_gain' => 100.0]);

        ProcessUserGamificationJob::dispatchSync($user->id);

        $this->assertSame(21, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);

        $activity->delete();

        ProcessUserGamificationJob::dispatchSync($user->id);

        $this->assertSame(0, XpEntry::query()->where('user_id', $user->id)->where('rule_key', 'sport_activity')->count());
        $this->assertSame(0, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);
    }

    #[Test]
    public function it_keeps_windowed_xp_for_a_source_in_the_start_of_day_slice(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(10));

        $user = User::factory()->create();
        SportActivity::factory()->create([
            'user_id' => $user->id,
            'distance' => 10000.0,
            'total_elevation_gain' => 100.0,
            'started_at' => now()->subDays(3)->startOfDay()->addHours(2),
        ]);

        ProcessUserGamificationJob::dispatchSync($user->id);

        $seededTotal = PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp;
        $this->assertSame(21, $seededTotal);

        ProcessUserGamificationJob::dispatchSync($user->id, now()->subDays(3));

        $this->assertSame(1, XpEntry::query()->where('user_id', $user->id)->where('rule_key', 'sport_activity')->count());
        $this->assertSame($seededTotal, PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);
    }

    #[Test]
    public function it_purges_a_decommissioned_rule_entry_within_the_window(): void
    {
        $user = User::factory()->create();
        XpEntry::factory()->create([
            'user_id' => $user->id,
            'rule_key' => 'retired_rule',
            'points' => 50,
            'occurred_at' => now()->subDay(),
        ]);

        ProcessUserGamificationJob::dispatchSync($user->id, now()->subDays(7));

        $this->assertSame(0, XpEntry::query()->where('user_id', $user->id)->where('rule_key', 'retired_rule')->count());
    }

    #[Test]
    public function it_quietly_skips_a_deleted_user(): void
    {
        ProcessUserGamificationJob::dispatchSync('01hzzzzzzzzzzzzzzzzzzzzzzz');

        $this->assertSame(0, XpEntry::query()->count());
    }

    #[Test]
    public function it_updates_the_streak_projections_after_the_ledger(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'started_at' => now()->subDay()]);
        SportActivity::factory()->create(['user_id' => $user->id, 'started_at' => now()]);

        ProcessUserGamificationJob::dispatchSync($user->id);

        $streak = Streak::query()->where('user_id', $user->id)->where('domain', GamificationDomain::Sport->value)->sole();
        $this->assertSame(2, $streak->current_count);
    }
}
