<?php

namespace Tests\Unit\Finance;

use Functional\Finance\Models\Position;
use Functional\Finance\Services\PerformanceCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PerformanceCalculatorTest extends TestCase
{
    private PerformanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new PerformanceCalculator;
    }

    #[Test]
    public function it_computes_the_value_and_performance_of_a_position(): void
    {
        $position = $this->makePosition(10.0, 100.0, 150.0);

        $result = $this->calculator->positionPerformance($position);

        $this->assertSame(1500.0, $result->currentValue);
        $this->assertSame(1000.0, $result->netInvested);
        $this->assertSame(500.0, $result->absoluteGain);
        $this->assertSame(50.0, $result->percentageGain);
    }

    #[Test]
    public function it_treats_a_position_without_price_as_zero_value(): void
    {
        $position = $this->makePosition(10.0, 100.0, null);

        $this->assertSame(0.0, $this->calculator->positionValue($position));
    }

    #[Test]
    public function it_aggregates_global_performance_across_positions(): void
    {
        $positions = new Collection([
            $this->makePosition(10.0, 100.0, 150.0),
            $this->makePosition(5.0, 200.0, 180.0),
        ]);

        $result = $this->calculator->globalPerformance($positions);

        $this->assertSame(2400.0, $result->currentValue);
        $this->assertSame(2000.0, $result->netInvested);
        $this->assertSame(400.0, $result->absoluteGain);
        $this->assertSame(20.0, $result->percentageGain);
    }

    #[Test]
    public function it_computes_allocation_weights_by_market_value(): void
    {
        $positions = new Collection([
            $this->makePosition(10.0, 100.0, 150.0, 'AAA'),
            $this->makePosition(5.0, 100.0, 100.0, 'BBB'),
        ]);

        $weights = $this->calculator->allocationWeights($positions);

        $this->assertSame(75.0, $weights['AAA']);
        $this->assertSame(25.0, $weights['BBB']);
    }

    #[Test]
    public function it_returns_empty_allocation_without_value(): void
    {
        $positions = new Collection([
            $this->makePosition(10.0, 100.0, null),
        ]);

        $this->assertSame([], $this->calculator->allocationWeights($positions));
    }

    private function makePosition(float $quantity, float $averageBuyPrice, ?float $currentPrice, string $symbol = 'AAA'): Position
    {
        return (new Position)->forceFill([
            'asset_symbol' => $symbol,
            'quantity' => $quantity,
            'average_buy_price' => $averageBuyPrice,
            'current_price' => $currentPrice,
        ]);
    }
}
