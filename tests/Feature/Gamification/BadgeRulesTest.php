<?php

namespace Tests\Feature\Gamification;

use Functional\Exploration\Models\ExploredCell;
use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Gamification\Badges\Rules\ExplorationCellsBadgeRule;
use Functional\Gamification\Badges\Rules\FinanceInvestedCapitalBadgeRule;
use Functional\Gamification\Badges\Rules\HealthMeasurementDaysBadgeRule;
use Functional\Gamification\Badges\Rules\HealthStreakBadgeRule;
use Functional\Gamification\Badges\Rules\MotoDistanceBadgeRule;
use Functional\Gamification\Badges\Rules\SportActivityCountBadgeRule;
use Functional\Gamification\Badges\Rules\SportDistanceBadgeRule;
use Functional\Gamification\Badges\Rules\SportStreakBadgeRule;
use Functional\Gamification\Badges\Rules\TodoStreakBadgeRule;
use Functional\Gamification\Badges\Rules\TodoTasksCompletedBadgeRule;
use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Streak;
use Functional\Health\Models\BodyMeasurement;
use Functional\Moto\Models\MotoRide;
use Functional\Sport\Models\SportActivity;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BadgeRulesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_measures_zero_for_a_user_without_data(): void
    {
        $user = User::factory()->create();

        foreach ($this->app->tagged('gamification.badge_rules') as $rule) {
            $this->assertSame(0.0, $rule->measure($user), $rule->key());
        }
    }

    #[Test]
    public function it_measures_sport_distance_in_kilometres(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 10000]);
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 5000]);
        SportActivity::factory()->create(['distance' => 99000]);

        $this->assertSame(15.0, $this->app->make(SportDistanceBadgeRule::class)->measure($user));
    }

    #[Test]
    public function it_counts_the_sport_activities_of_the_user(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->count(3)->create(['user_id' => $user->id]);
        SportActivity::factory()->count(2)->create();

        $this->assertSame(3.0, $this->app->make(SportActivityCountBadgeRule::class)->measure($user));
    }

    #[Test]
    public function it_counts_distinct_health_measurement_days(): void
    {
        $user = User::factory()->create();
        BodyMeasurement::factory()->count(3)->create(['user_id' => $user->id, 'measured_at' => now()->subDay()->setTime(8, 0)]);
        BodyMeasurement::factory()->create(['user_id' => $user->id, 'measured_at' => now()->subDays(4)->setTime(9, 0)]);
        BodyMeasurement::factory()->count(2)->create(['measured_at' => now()->subDays(7)]);

        $this->assertSame(2.0, $this->app->make(HealthMeasurementDaysBadgeRule::class)->measure($user));
    }

    #[Test]
    public function it_measures_the_net_invested_capital_of_the_user(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->create(['user_id' => $user->id]);
        InvestmentTransaction::factory()->create(['position_id' => $position->id, 'user_id' => $user->id, 'type' => TransactionType::Buy, 'quantity' => 10, 'unit_price' => 100]);
        InvestmentTransaction::factory()->create(['position_id' => $position->id, 'user_id' => $user->id, 'type' => TransactionType::Sell, 'quantity' => 2, 'unit_price' => 100]);
        $otherPosition = Position::factory()->create();
        InvestmentTransaction::factory()->create(['position_id' => $otherPosition->id, 'user_id' => $otherPosition->user_id, 'type' => TransactionType::Buy, 'quantity' => 50, 'unit_price' => 100]);

        $this->assertSame(800.0, $this->app->make(FinanceInvestedCapitalBadgeRule::class)->measure($user));
    }

    #[Test]
    public function it_measures_moto_distance_in_kilometres(): void
    {
        $user = User::factory()->create();
        MotoRide::factory()->create(['user_id' => $user->id, 'distance' => 120.5]);
        MotoRide::factory()->create(['user_id' => $user->id, 'distance' => 79.5]);
        MotoRide::factory()->create(['distance' => 500]);

        $this->assertSame(200.0, $this->app->make(MotoDistanceBadgeRule::class)->measure($user));
    }

    #[Test]
    public function it_counts_only_completed_tasks(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(4)->completed()->create(['user_id' => $user->id]);
        Task::factory()->create(['user_id' => $user->id, 'completed_at' => null]);
        Task::factory()->count(2)->completed()->create();

        $this->assertSame(4.0, $this->app->make(TodoTasksCompletedBadgeRule::class)->measure($user));
    }

    #[Test]
    public function it_counts_the_explored_cells_of_the_user(): void
    {
        $user = User::factory()->create();
        ExploredCell::factory()->count(5)->create(['user_id' => $user->id]);
        ExploredCell::factory()->count(3)->create();

        $this->assertSame(5.0, $this->app->make(ExplorationCellsBadgeRule::class)->measure($user));
    }

    #[Test]
    public function it_measures_the_best_streak_of_each_domain(): void
    {
        $user = User::factory()->create();
        $bestCount = faker()->number(10, 50);
        Streak::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Sport, 'current_count' => 1, 'best_count' => $bestCount]);
        Streak::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Health, 'current_count' => 2, 'best_count' => 7]);
        Streak::factory()->create(['domain' => GamificationDomain::Sport, 'best_count' => 400]);
        Streak::factory()->create(['domain' => GamificationDomain::Todo, 'best_count' => 400]);

        $this->assertSame((float) $bestCount, $this->app->make(SportStreakBadgeRule::class)->measure($user));
        $this->assertSame(7.0, $this->app->make(HealthStreakBadgeRule::class)->measure($user));
        $this->assertSame(0.0, $this->app->make(TodoStreakBadgeRule::class)->measure($user));
    }

    #[Test]
    public function it_tags_every_rule_with_unique_keys_and_configured_thresholds(): void
    {
        $rules = collect($this->app->tagged('gamification.badge_rules'));

        $this->assertCount(10, $rules);
        $this->assertCount(10, $rules->map(fn (BadgeRule $rule): string => $rule->key())->unique());

        foreach ($rules as $rule) {
            foreach (BadgeTier::cases() as $tier) {
                $this->assertNotNull(config("gamification.badges.thresholds.{$rule->key()}.{$tier->value}"), "{$rule->key()} {$tier->value}");
            }
        }
    }
}
