<?php

namespace Tests\Unit\Finance;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Services\CapitalCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CapitalCalculatorTest extends TestCase
{
    private CapitalCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new CapitalCalculator;
    }

    #[Test]
    public function it_computes_invested_sold_and_net_capital(): void
    {
        $breakdown = $this->calculator->breakdown($this->transactions());

        $this->assertSame(2000.0, $breakdown->investedAmount);
        $this->assertSame(600.0, $breakdown->soldProceeds);
        $this->assertSame(1400.0, $breakdown->netInvested);
    }

    #[Test]
    public function it_returns_zero_capital_for_no_transactions(): void
    {
        $breakdown = $this->calculator->breakdown(new Collection);

        $this->assertSame(0.0, $breakdown->investedAmount);
        $this->assertSame(0.0, $breakdown->netInvested);
    }

    #[Test]
    public function it_computes_realized_gain_with_average_cost_basis(): void
    {
        $transactions = new Collection([
            $this->makeTransaction(TransactionType::Buy, 10.0, 100.0, '2026-01-01'),
            $this->makeTransaction(TransactionType::Buy, 10.0, 200.0, '2026-02-01'),
            $this->makeTransaction(TransactionType::Sell, 5.0, 300.0, '2026-03-01'),
        ]);

        $this->assertSame(750.0, $this->calculator->realizedGain($transactions));
    }

    #[Test]
    public function it_returns_zero_realized_gain_without_sells(): void
    {
        $transactions = new Collection([
            $this->makeTransaction(TransactionType::Buy, 10.0, 100.0, '2026-01-01'),
        ]);

        $this->assertSame(0.0, $this->calculator->realizedGain($transactions));
    }

    /**
     * Build a hand-crafted collection of transactions.
     *
     * @return Collection<int, InvestmentTransaction>
     */
    private function transactions(): Collection
    {
        return new Collection([
            $this->makeTransaction(TransactionType::Buy, 10.0, 100.0, '2026-01-01'),
            $this->makeTransaction(TransactionType::Buy, 10.0, 100.0, '2026-01-15'),
            $this->makeTransaction(TransactionType::Sell, 4.0, 150.0, '2026-02-01'),
        ]);
    }

    private function makeTransaction(TransactionType $type, float $quantity, float $unitPrice, string $executedAt): InvestmentTransaction
    {
        return (new InvestmentTransaction)->forceFill([
            'type' => $type,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'executed_at' => Carbon::parse($executedAt),
        ]);
    }
}
