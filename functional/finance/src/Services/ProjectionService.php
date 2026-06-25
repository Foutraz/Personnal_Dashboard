<?php

namespace Functional\Finance\Services;

use Functional\Finance\Services\Dto\ProjectionParameters;

class ProjectionService
{
    /**
     * Project a portfolio forward year by year with optional monthly contributions.
     *
     * @return array<int, array{year: int, contributed: float, value: float, gain: float}>
     */
    public function project(ProjectionParameters $parameters): array
    {
        $monthlyRate = $parameters->annualReturnRate / 100 / 12;
        $value = $parameters->startingValue;
        $contributed = $parameters->startingValue;
        $points = [];

        for ($year = 1; $year <= $parameters->years; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $value = ($value + $parameters->monthlyContribution) * (1 + $monthlyRate);
                $contributed += $parameters->monthlyContribution;
            }

            $points[] = [
                'year' => $year,
                'contributed' => round($contributed, 2),
                'value' => round($value, 2),
                'gain' => round($value - $contributed, 2),
            ];
        }

        return $points;
    }
}
