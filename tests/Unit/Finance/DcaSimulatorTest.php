<?php

namespace Tests\Unit\Finance;

use Functional\Finance\Enums\DcaFrequency;
use Functional\Finance\Exceptions\InvalidSimulationParametersException;
use Functional\Finance\Services\DcaSimulator;
use Functional\Finance\Services\Dto\DcaParameters;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DcaSimulatorTest extends TestCase
{
    private DcaSimulator $simulator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->simulator = new DcaSimulator;
    }

    #[Test]
    public function it_accumulates_invested_capital_with_zero_return(): void
    {
        $parameters = DcaParameters::create(100.0, DcaFrequency::Monthly, 1, 0.0);

        $points = $this->simulator->simulate($parameters);

        $this->assertCount(12, $points);
        $this->assertSame(1200.0, $points[11]['invested']);
        $this->assertSame(1200.0, $points[11]['value']);
        $this->assertSame(0.0, $points[11]['gain']);
    }

    #[Test]
    public function it_grows_the_value_above_the_invested_capital_with_positive_return(): void
    {
        $parameters = DcaParameters::create(100.0, DcaFrequency::Monthly, 10, 7.0);

        $summary = $this->simulator->yearlySummary($parameters);

        $this->assertCount(10, $summary);
        $this->assertSame(12000.0, $summary[9]['invested']);
        $this->assertGreaterThan(12000.0, $summary[9]['value']);
        $this->assertSame(round($summary[9]['value'] - $summary[9]['invested'], 2), $summary[9]['gain']);
    }

    #[Test]
    public function it_scales_period_count_with_the_frequency(): void
    {
        $weekly = DcaParameters::create(50.0, DcaFrequency::Weekly, 2, 0.0);
        $quarterly = DcaParameters::create(50.0, DcaFrequency::Quarterly, 2, 0.0);

        $this->assertCount(104, $this->simulator->simulate($weekly));
        $this->assertCount(8, $this->simulator->simulate($quarterly));
    }

    #[Test]
    public function it_rejects_a_non_positive_duration(): void
    {
        $this->expectException(InvalidSimulationParametersException::class);

        DcaParameters::create(100.0, DcaFrequency::Monthly, 0, 7.0);
    }

    #[Test]
    public function it_rejects_a_negative_contribution_amount(): void
    {
        $this->expectException(InvalidSimulationParametersException::class);

        DcaParameters::create(-10.0, DcaFrequency::Monthly, 5, 7.0);
    }
}
