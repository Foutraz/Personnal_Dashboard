<?php

namespace Functional\Gamification\Challenges;

use Functional\Gamification\Services\Dto\ChallengeSettings;
use Functional\Gamification\Services\Dto\ChallengeTarget;
use Functional\Gamification\Services\Dto\ChallengeTemplateSettings;

class ChallengeTargetCalculator
{
    private const BASELINE_DECIMALS = 2;

    private const NOISE_DECIMALS = 6;

    /**
     * @param  list<float>  $weeklyValues
     */
    public function target(ChallengeTemplateSettings $template, ChallengeSettings $settings, array $weeklyValues): ?ChallengeTarget
    {
        if ($weeklyValues === [] || $this->activeWeeks($weeklyValues) < $settings->minActiveWeeks) {
            return null;
        }

        $baseline = round($this->median($weeklyValues), self::BASELINE_DECIMALS);
        $stretched = round($baseline * (1 + $settings->stretchRatio), self::NOISE_DECIMALS);
        $stepped = $this->roundUpToStep($stretched, $template->step);

        return new ChallengeTarget($baseline, min(max($stepped, $template->floor), $template->cap));
    }

    /**
     * @param  list<float>  $weeklyValues
     */
    private function activeWeeks(array $weeklyValues): int
    {
        return count(array_filter($weeklyValues, fn (float $weeklyValue): bool => $weeklyValue > 0.0));
    }

    /**
     * @param  non-empty-list<float>  $weeklyValues
     */
    private function median(array $weeklyValues): float
    {
        sort($weeklyValues);
        $middle = intdiv(count($weeklyValues), 2);

        if (count($weeklyValues) % 2 === 1) {
            return $weeklyValues[$middle];
        }

        return ($weeklyValues[$middle - 1] + $weeklyValues[$middle]) / 2;
    }

    private function roundUpToStep(float $amount, float $step): float
    {
        $stepCount = ceil(round($amount / $step, self::NOISE_DECIMALS));

        return round($stepCount * $step, self::NOISE_DECIMALS);
    }
}
