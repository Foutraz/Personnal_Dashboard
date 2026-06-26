<?php

namespace Tests\Unit\Moto;

use Foutraz\Weather\Dto\ForecastEntry;
use Functional\Moto\Enums\RideRating;
use Functional\Moto\Services\MotoFriendlyScore;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MotoFriendlyScoreTest extends TestCase
{
    private function score(): MotoFriendlyScore
    {
        return new MotoFriendlyScore;
    }

    #[Test]
    public function it_returns_a_high_score_for_clear_warm_calm_conditions(): void
    {
        $condition = $this->score()->forValues(0.0, 0.0, 2.0, 3.0, 22.0, 10000);

        $this->assertSame(100, $condition->score);
        $this->assertSame(RideRating::Excellent, $condition->rating);
        $this->assertContains('Conditions idéales pour rouler.', $condition->reasons);
    }

    #[Test]
    public function it_returns_a_low_score_for_heavy_rain_and_high_probability(): void
    {
        $condition = $this->score()->forValues(0.9, 8.0, 3.0, null, 20.0, 10000);

        $this->assertLessThan(50, $condition->score);
        $this->assertContains('Fortes précipitations attendues.', $condition->reasons);
    }

    #[Test]
    public function it_penalises_strong_wind_and_gusts(): void
    {
        $calm = $this->score()->forValues(0.0, 0.0, 2.0, 3.0, 22.0, 10000);
        $windy = $this->score()->forValues(0.0, 0.0, 16.0, 22.0, 22.0, 10000);

        $this->assertLessThan($calm->score, $windy->score);
        $this->assertContains('Vent fort et rafales.', $windy->reasons);
    }

    #[Test]
    public function it_penalises_cold_temperatures(): void
    {
        $condition = $this->score()->forValues(0.0, 0.0, 2.0, 3.0, 1.0, 10000);

        $this->assertLessThan(100, $condition->score);
        $this->assertContains('Température basse, risque de gel.', $condition->reasons);
    }

    #[Test]
    public function it_penalises_low_visibility(): void
    {
        $condition = $this->score()->forValues(0.0, 0.0, 2.0, 3.0, 22.0, 1000);

        $this->assertLessThan(100, $condition->score);
        $this->assertContains('Visibilité très réduite.', $condition->reasons);
    }

    #[Test]
    public function it_assigns_the_dangerous_label_to_the_worst_conditions(): void
    {
        $condition = $this->score()->forValues(1.0, 10.0, 20.0, 25.0, -5.0, 500);

        $this->assertSame(0, $condition->score);
        $this->assertSame(RideRating::Dangerous, $condition->rating);
    }

    #[Test]
    public function it_scores_a_forecast_entry(): void
    {
        $entry = new ForecastEntry(
            new \DateTimeImmutable('2026-06-26T12:00:00Z'),
            21.0,
            21.0,
            2.0,
            3.0,
            0.0,
            null,
            10,
            10000,
            'Clear',
            'clear sky',
        );

        $condition = $this->score()->forForecastEntry($entry);

        $this->assertSame(100, $condition->score);
        $this->assertSame(RideRating::Excellent, $condition->rating);
    }

    #[Test]
    public function it_maps_boundary_scores_to_the_expected_labels(): void
    {
        $thresholds = ['excellent' => 80, 'good' => 65, 'average' => 45, 'bad' => 25];

        $this->assertSame(RideRating::Excellent, RideRating::fromScore(80, $thresholds));
        $this->assertSame(RideRating::Good, RideRating::fromScore(65, $thresholds));
        $this->assertSame(RideRating::Average, RideRating::fromScore(45, $thresholds));
        $this->assertSame(RideRating::Bad, RideRating::fromScore(25, $thresholds));
        $this->assertSame(RideRating::Dangerous, RideRating::fromScore(24, $thresholds));
    }
}
