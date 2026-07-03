<?php

namespace Tests\Feature\Gamification;

use Functional\Exploration\Models\ExploredCell;
use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\BankTransaction;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Gamification\Xp\Rules\ExplorationCellXpRule;
use Functional\Gamification\Xp\Rules\FinanceMonthlyXpRule;
use Functional\Gamification\Xp\Rules\HealthMeasurementDayXpRule;
use Functional\Gamification\Xp\Rules\MotoRideXpRule;
use Functional\Gamification\Xp\Rules\SportActivityXpRule;
use Functional\Gamification\Xp\Rules\TodoTaskCompletedXpRule;
use Functional\Health\Models\BodyMeasurement;
use Functional\Moto\Models\MotoRide;
use Functional\Sport\Models\SportActivity;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class XpRulesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_awards_sport_activity_points_with_capped_bonuses(): void
    {
        $user = User::factory()->create();
        $activity = SportActivity::factory()->create([
            'user_id' => $user->id,
            'distance' => 25000.0,
            'total_elevation_gain' => 500.0,
        ]);

        $awards = $this->app->make(SportActivityXpRule::class)->awards($user, null);

        $this->assertCount(1, $awards);
        $award = $awards->first();
        $this->assertSame(10 + 25 + 5, $award->points);
        $this->assertSame(SportActivity::class, $award->sourceType);
        $this->assertSame($activity->id, $award->sourceId);
    }

    #[Test]
    public function it_caps_sport_distance_and_elevation_bonuses(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create([
            'user_id' => $user->id,
            'distance' => 100000.0,
            'total_elevation_gain' => 5000.0,
        ]);

        $awards = $this->app->make(SportActivityXpRule::class)->awards($user, null);

        $this->assertSame(10 + 30 + 20, $awards->first()->points);
    }

    #[Test]
    public function it_ignores_sport_activities_of_other_users_and_before_since(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'started_at' => now()->subMonths(2)]);
        SportActivity::factory()->create(['started_at' => now()->subDay()]);

        $awards = $this->app->make(SportActivityXpRule::class)->awards($user, now()->subDays(7));

        $this->assertCount(0, $awards);
    }

    #[Test]
    public function it_awards_completed_tasks_capped_per_day(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(12)->completed()->create(['user_id' => $user->id, 'completed_at' => now()->subDay()]);
        Task::factory()->create(['user_id' => $user->id, 'completed_at' => null]);

        $awards = $this->app->make(TodoTaskCompletedXpRule::class)->awards($user, null);

        $this->assertCount(10, $awards);
        $this->assertSame(3, $awards->first()->points);
        $this->assertSame(Task::class, $awards->first()->sourceType);
    }

    #[Test]
    public function it_awards_exploration_cells_per_day_with_a_cap(): void
    {
        $user = User::factory()->create();
        ExploredCell::factory()->count(30)->create(['user_id' => $user->id, 'first_seen_at' => now()->subDays(2)]);
        ExploredCell::factory()->count(3)->create(['user_id' => $user->id, 'first_seen_at' => now()->subDays(3)]);

        $awards = $this->app->make(ExplorationCellXpRule::class)->awards($user, null);

        $this->assertCount(2, $awards);
        $byDay = $awards->keyBy(fn (XpAward $award): string => $award->sourceId);
        $this->assertSame(40, $byDay[now()->subDays(2)->toDateString()]->points);
        $this->assertSame(6, $byDay[now()->subDays(3)->toDateString()]->points);
    }

    #[Test]
    public function it_defers_exploration_cells_discovered_today(): void
    {
        $user = User::factory()->create();
        ExploredCell::factory()->count(5)->create(['user_id' => $user->id, 'first_seen_at' => now()]);

        $awards = $this->app->make(ExplorationCellXpRule::class)->awards($user, null);

        $this->assertCount(0, $awards);
    }

    #[Test]
    public function it_awards_a_single_entry_per_measurement_day(): void
    {
        $user = User::factory()->create();
        BodyMeasurement::factory()->count(3)->create(['user_id' => $user->id, 'measured_at' => now()->subDays(1)->setTime(8, 0)]);
        BodyMeasurement::factory()->create(['user_id' => $user->id, 'measured_at' => now()->subDays(4)]);

        $awards = $this->app->make(HealthMeasurementDayXpRule::class)->awards($user, null);

        $this->assertCount(2, $awards);
        $this->assertSame([5, 5], $awards->pluck('points')->all());
    }

    #[Test]
    public function it_awards_moto_rides_with_capped_distance_bonus(): void
    {
        $user = User::factory()->create();
        MotoRide::factory()->create(['user_id' => $user->id, 'distance' => 100]);

        $awards = $this->app->make(MotoRideXpRule::class)->awards($user, null);

        $this->assertCount(1, $awards);
        $this->assertSame(10 + 5, $awards->first()->points);
    }

    #[Test]
    public function it_awards_a_positive_savings_month_once(): void
    {
        $user = User::factory()->create();
        $month = now()->subMonth()->startOfMonth();
        BankTransaction::factory()->create(['user_id' => $user->id, 'amount' => 2500, 'booked_at' => $month->copy()->addDays(2)]);
        BankTransaction::factory()->count(5)->create(['user_id' => $user->id, 'amount' => -100, 'booked_at' => $month->copy()->addDays(10)]);

        $awards = $this->app->make(FinanceMonthlyXpRule::class)->awards($user, null);

        $this->assertCount(1, $awards);
        $this->assertSame(20, $awards->first()->points);
        $this->assertSame($month->format('Y-m'), $awards->first()->sourceId);
    }

    #[Test]
    public function it_adds_an_investment_bonus_to_the_month(): void
    {
        $user = User::factory()->create();
        $month = now()->subMonth()->startOfMonth();
        BankTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'booked_at' => $month->copy()->addDays(3)]);
        $position = Position::factory()->create(['user_id' => $user->id]);
        InvestmentTransaction::factory()->create(['position_id' => $position->id, 'type' => TransactionType::Buy, 'executed_at' => $month->copy()->addDays(5)]);

        $awards = $this->app->make(FinanceMonthlyXpRule::class)->awards($user, null);

        $this->assertSame(30, $awards->first()->points);
    }

    #[Test]
    public function it_skips_negative_months_and_the_current_month(): void
    {
        $user = User::factory()->create();
        BankTransaction::factory()->create(['user_id' => $user->id, 'amount' => -800, 'booked_at' => now()->subMonth()->startOfMonth()->addDays(4)]);
        BankTransaction::factory()->create(['user_id' => $user->id, 'amount' => 900, 'booked_at' => now()->startOfMonth()->addDay()]);

        $awards = $this->app->make(FinanceMonthlyXpRule::class)->awards($user, null);

        $this->assertCount(0, $awards);
    }

    #[Test]
    public function it_tags_every_rule_in_the_container(): void
    {
        $rules = $this->app->tagged('gamification.xp_rules');

        $keys = collect($rules)->map(fn (object $rule): string => $rule->key());

        $this->assertCount(6, $keys);
        $this->assertSame($keys->count(), $keys->unique()->count());
    }
}
