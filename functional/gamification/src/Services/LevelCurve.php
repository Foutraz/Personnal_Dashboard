<?php

namespace Functional\Gamification\Services;

class LevelCurve
{
    /**
     * Get the cumulative xp required to reach the given level.
     */
    public function xpForLevel(int $level): int
    {
        return (int) round($this->base() * pow($level - 1, $this->exponent()));
    }

    /**
     * Resolve the level reached for the given xp total, correcting float drift against the exact curve.
     */
    public function levelForXp(int $xp): int
    {
        $level = max(1, (int) floor(pow($xp / $this->base(), 1 / $this->exponent())) + 1);

        while ($level > 1 && $this->xpForLevel($level) > $xp) {
            $level--;
        }

        while ($this->xpForLevel($level + 1) <= $xp) {
            $level++;
        }

        return $level;
    }

    /**
     * Get the configured xp cost of the first level step.
     */
    private function base(): int
    {
        return (int) config('gamification.level_curve.base');
    }

    /**
     * Get the configured growth exponent of the curve.
     */
    private function exponent(): float
    {
        return (float) config('gamification.level_curve.exponent');
    }
}
