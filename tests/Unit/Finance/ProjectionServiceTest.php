<?php

namespace Tests\Unit\Finance;

use Functional\Finance\Exceptions\InvalidSimulationParametersException;
use Functional\Finance\Services\Dto\ProjectionParameters;
use Functional\Finance\Services\ProjectionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectionServiceTest extends TestCase
{
    private ProjectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProjectionService;
    }

    #[Test]
    public function it_keeps_a_lump_sum_without_contributions_at_zero_return(): void
    {
        $parameters = ProjectionParameters::create(10000.0, 0.0, 5, 0.0);

        $points = $this->service->project($parameters);

        $this->assertCount(5, $points);
        $this->assertSame(10000.0, $points[4]['value']);
        $this->assertSame(10000.0, $points[4]['contributed']);
        $this->assertSame(0.0, $points[4]['gain']);
    }

    #[Test]
    public function it_adds_monthly_contributions_to_the_contributed_total(): void
    {
        $parameters = ProjectionParameters::create(0.0, 100.0, 2, 0.0);

        $points = $this->service->project($parameters);

        $this->assertSame(2400.0, $points[1]['contributed']);
        $this->assertSame(2400.0, $points[1]['value']);
    }

    #[Test]
    public function it_compounds_a_lump_sum_with_a_positive_return(): void
    {
        $parameters = ProjectionParameters::create(10000.0, 0.0, 1, 12.0);

        $points = $this->service->project($parameters);

        $this->assertGreaterThan(11000.0, $points[0]['value']);
        $this->assertSame(round($points[0]['value'] - 10000.0, 2), $points[0]['gain']);
    }

    #[Test]
    public function it_rejects_a_non_positive_duration(): void
    {
        $this->expectException(InvalidSimulationParametersException::class);

        ProjectionParameters::create(1000.0, 100.0, 0, 7.0);
    }

    #[Test]
    public function it_rejects_a_negative_monthly_contribution(): void
    {
        $this->expectException(InvalidSimulationParametersException::class);

        ProjectionParameters::create(1000.0, -50.0, 5, 7.0);
    }
}
