<?php

namespace Tests\Feature\Gamification;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\BankTransaction;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Gamification\Xp\Rules\FinanceMonthlyXpRule;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceMonthInvestmentXpTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-15 12:00:00';

    private const ACCOUNT_OPENED_AT = '2024-01-10 09:00:00';

    private const INVESTMENT_POINTS = 10;

    private const SAVINGS_POINTS = 20;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
    }

    private function accountOpenedAt(string $createdAt = self::ACCOUNT_OPENED_AT): User
    {
        return User::factory()->create(['created_at' => Carbon::parse($createdAt, 'UTC')]);
    }

    private function positionOpenedAt(User $user, string $createdAt = self::ACCOUNT_OPENED_AT): Position
    {
        return Position::factory()->create(['user_id' => $user->id, 'created_at' => Carbon::parse($createdAt, 'UTC')]);
    }

    private function trade(Position $position, string $executedAt, float $quantity, float $unitPrice, TransactionType $type = TransactionType::Buy): InvestmentTransaction
    {
        return InvestmentTransaction::factory()->create([
            'position_id' => $position->id,
            'user_id' => $position->user_id,
            'type' => $type,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'executed_at' => Carbon::parse($executedAt, 'UTC'),
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function awardedMonthsOf(User $user): Collection
    {
        return $this->app->make(FinanceMonthlyXpRule::class)->awards($user, null)->map(fn ($award): string => $award->sourceId);
    }

    #[Test]
    public function it_awards_nothing_for_hundreds_of_tiny_buys_backdated_from_a_new_account(): void
    {
        $user = $this->accountOpenedAt(self::NOW);
        $position = $this->positionOpenedAt($user, self::NOW);

        for ($monthsBack = 1; $monthsBack <= 680; $monthsBack++) {
            $this->trade($position, Carbon::parse(self::NOW, 'UTC')->startOfMonth()->subMonths($monthsBack)->addDays(3)->toDateTimeString(), 0.001, 1);
        }

        $this->assertSame([], $this->awardedMonthsOf($user)->all());
    }

    #[Test]
    public function it_awards_nothing_for_tiny_buys_made_after_the_account_existed(): void
    {
        $user = $this->accountOpenedAt();
        $position = $this->positionOpenedAt($user);

        for ($monthsBack = 1; $monthsBack <= 12; $monthsBack++) {
            $this->trade($position, Carbon::parse(self::NOW, 'UTC')->startOfMonth()->subMonths($monthsBack)->addDays(3)->toDateTimeString(), 0.001, 1);
        }

        $this->assertSame([], $this->awardedMonthsOf($user)->all());
    }

    #[Test]
    public function it_awards_a_month_with_a_real_buy(): void
    {
        $user = $this->accountOpenedAt();
        $position = $this->positionOpenedAt($user);
        $this->trade($position, '2026-09-12 10:00:00', 1, 50);

        $awards = $this->app->make(FinanceMonthlyXpRule::class)->awards($user, null);

        $this->assertCount(1, $awards);
        $this->assertSame('2026-09', $awards->first()->sourceId);
        $this->assertSame(self::INVESTMENT_POINTS, $awards->first()->points);
    }

    #[Test]
    public function it_awards_a_month_whose_net_bought_amount_equals_the_minimum(): void
    {
        $user = $this->accountOpenedAt();
        $position = $this->positionOpenedAt($user);
        $this->trade($position, '2026-09-12 10:00:00', 0.1, 100);

        $this->assertSame(['2026-09'], $this->awardedMonthsOf($user)->all());
    }

    #[Test]
    public function it_refuses_a_month_one_cent_below_the_minimum(): void
    {
        $user = $this->accountOpenedAt();
        $position = $this->positionOpenedAt($user);
        $this->trade($position, '2026-09-12 10:00:00', 1, 9.99);

        $this->assertSame([], $this->awardedMonthsOf($user)->all());
    }

    #[Test]
    public function it_adds_up_the_buys_of_the_same_month(): void
    {
        $user = $this->accountOpenedAt();
        $position = $this->positionOpenedAt($user);
        $this->trade($position, '2026-09-02 10:00:00', 1, 4);
        $this->trade($position, '2026-09-20 10:00:00', 1, 6);

        $this->assertSame(['2026-09'], $this->awardedMonthsOf($user)->all());
    }

    #[Test]
    public function it_nets_the_sells_of_the_month_against_the_buys(): void
    {
        $user = $this->accountOpenedAt();
        $position = $this->positionOpenedAt($user);
        $this->trade($position, '2026-09-02 10:00:00', 1, 50);
        $this->trade($position, '2026-09-20 10:00:00', 1, 45, TransactionType::Sell);
        $this->trade($position, '2026-08-02 10:00:00', 1, 50);
        $this->trade($position, '2026-08-20 10:00:00', 1, 30, TransactionType::Sell);

        $this->assertSame(['2026-08'], $this->awardedMonthsOf($user)->all());
    }

    #[Test]
    public function it_follows_the_configured_minimum(): void
    {
        config(['gamification.xp.finance.investment_minimum_net_bought' => 100]);
        $user = $this->accountOpenedAt();
        $position = $this->positionOpenedAt($user);
        $this->trade($position, '2026-09-12 10:00:00', 1, 50);
        $this->trade($position, '2026-08-12 10:00:00', 1, 100);

        $this->assertSame(['2026-08'], $this->awardedMonthsOf($user)->all());
    }

    #[Test]
    public function it_ignores_a_buy_executed_before_the_month_the_position_was_created(): void
    {
        $user = $this->accountOpenedAt();
        $position = $this->positionOpenedAt($user, '2026-08-20 09:00:00');
        $this->trade($position, '2026-06-12 10:00:00', 1, 500);
        $this->trade($position, '2026-07-31 23:00:00', 1, 500);
        $this->trade($position, '2026-08-02 10:00:00', 1, 500);

        $this->assertSame(['2026-08'], $this->awardedMonthsOf($user)->all());
    }

    #[Test]
    public function it_ignores_a_buy_executed_before_the_month_the_account_was_created(): void
    {
        $user = $this->accountOpenedAt('2026-08-20 09:00:00');
        $position = $this->positionOpenedAt($user, '2026-01-05 09:00:00');
        $this->trade($position, '2026-06-12 10:00:00', 1, 500);
        $this->trade($position, '2026-07-31 23:00:00', 1, 500);
        $this->trade($position, '2026-08-02 10:00:00', 1, 500);

        $this->assertSame(['2026-08'], $this->awardedMonthsOf($user)->all());
    }

    #[Test]
    public function it_checks_each_buy_against_its_own_position(): void
    {
        $user = $this->accountOpenedAt();
        $oldPosition = $this->positionOpenedAt($user, '2025-01-05 09:00:00');
        $recentPosition = $this->positionOpenedAt($user, '2026-09-05 09:00:00');
        $this->trade($oldPosition, '2026-07-12 10:00:00', 1, 3);
        $this->trade($recentPosition, '2026-07-12 10:00:00', 1, 500);

        $this->assertSame([], $this->awardedMonthsOf($user)->all());
    }

    #[Test]
    public function it_still_awards_the_savings_of_a_month_that_precedes_the_account(): void
    {
        $user = $this->accountOpenedAt('2026-09-20 09:00:00');
        BankTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'booked_at' => Carbon::parse('2026-06-10 10:00:00', 'UTC')]);

        $awards = $this->app->make(FinanceMonthlyXpRule::class)->awards($user, null);

        $this->assertCount(1, $awards);
        $this->assertSame(self::SAVINGS_POINTS, $awards->first()->points);
    }

    #[Test]
    public function it_keeps_the_savings_points_when_the_buys_of_the_month_are_too_small(): void
    {
        $user = $this->accountOpenedAt();
        $position = $this->positionOpenedAt($user);
        BankTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'booked_at' => Carbon::parse('2026-09-10 10:00:00', 'UTC')]);
        $this->trade($position, '2026-09-12 10:00:00', 0.001, 1);

        $awards = $this->app->make(FinanceMonthlyXpRule::class)->awards($user, null);

        $this->assertSame(self::SAVINGS_POINTS, $awards->first()->points);
    }

    #[Test]
    public function it_adds_both_kinds_of_points_for_a_month_with_savings_and_a_real_buy(): void
    {
        $user = $this->accountOpenedAt();
        $position = $this->positionOpenedAt($user);
        BankTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'booked_at' => Carbon::parse('2026-09-10 10:00:00', 'UTC')]);
        $this->trade($position, '2026-09-12 10:00:00', 1, 50);

        $awards = $this->app->make(FinanceMonthlyXpRule::class)->awards($user, null);

        $this->assertSame(self::SAVINGS_POINTS + self::INVESTMENT_POINTS, $awards->first()->points);
    }

    #[Test]
    public function it_never_counts_the_running_month(): void
    {
        $user = $this->accountOpenedAt();
        $position = $this->positionOpenedAt($user);
        $this->trade($position, '2026-10-03 10:00:00', 1, 500);

        $this->assertSame([], $this->awardedMonthsOf($user)->all());
    }

    #[Test]
    public function it_leaves_the_buys_of_another_user_out_of_the_month(): void
    {
        $user = $this->accountOpenedAt();
        $other = $this->accountOpenedAt();
        $otherPosition = $this->positionOpenedAt($other);
        $this->trade($otherPosition, '2026-09-12 10:00:00', 1, 500);

        $this->assertSame([], $this->awardedMonthsOf($user)->all());
    }
}
