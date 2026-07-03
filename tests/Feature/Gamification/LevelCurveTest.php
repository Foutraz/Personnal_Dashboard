<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Services\LevelCurve;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LevelCurveTest extends TestCase
{
    #[Test]
    public function it_computes_the_cumulative_xp_required_per_level(): void
    {
        $curve = $this->app->make(LevelCurve::class);

        $this->assertSame(0, $curve->xpForLevel(1));
        $this->assertSame(250, $curve->xpForLevel(2));
        $this->assertSame(6750, $curve->xpForLevel(10));
    }

    #[Test]
    public function it_resolves_the_level_reached_for_a_given_xp_total(): void
    {
        $curve = $this->app->make(LevelCurve::class);

        $this->assertSame(1, $curve->levelForXp(0));
        $this->assertSame(1, $curve->levelForXp(249));
        $this->assertSame(2, $curve->levelForXp(250));
        $this->assertSame(9, $curve->levelForXp(6749));
        $this->assertSame(10, $curve->levelForXp(6750));
    }

    #[Test]
    public function it_stays_consistent_between_both_directions(): void
    {
        $curve = $this->app->make(LevelCurve::class);

        foreach (range(1, 50) as $level) {
            $this->assertSame($level, $curve->levelForXp($curve->xpForLevel($level)));
            $this->assertGreaterThan($curve->xpForLevel($level), $curve->xpForLevel($level + 1));
        }
    }
}
