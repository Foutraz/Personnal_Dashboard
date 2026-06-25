<?php

namespace Functional\Finance\Services;

use Functional\Finance\Services\Dto\DcaParameters;

class DcaSimulator
{
    /**
     * Simulate the invested-versus-value curve of a periodic investment plan.
     *
     * @return array<int, array{period: int, invested: float, value: float, gain: float}>
     */
    public function simulate(DcaParameters $parameters): array
    {
        $periodsPerYear = $parameters->frequency->periodsPerYear();
        $totalPeriods = $periodsPerYear * $parameters->years;
        $periodRate = $parameters->annualReturnRate / 100 / $periodsPerYear;

        $invested = 0.0;
        $value = 0.0;
        $points = [];

        for ($period = 1; $period <= $totalPeriods; $period++) {
            $value = ($value + $parameters->periodicAmount) * (1 + $periodRate);
            $invested += $parameters->periodicAmount;

            $points[] = [
                'period' => $period,
                'invested' => round($invested, 2),
                'value' => round($value, 2),
                'gain' => round($value - $invested, 2),
            ];
        }

        return $points;
    }

    /**
     * Reduce the per-period simulation into one cumulative point per year.
     *
     * @return array<int, array{year: int, invested: float, value: float, gain: float}>
     */
    public function yearlySummary(DcaParameters $parameters): array
    {
        $points = $this->simulate($parameters);
        $periodsPerYear = $parameters->frequency->periodsPerYear();
        $summary = [];

        for ($year = 1; $year <= $parameters->years; $year++) {
            $point = $points[$year * $periodsPerYear - 1];

            $summary[] = [
                'year' => $year,
                'invested' => $point['invested'],
                'value' => $point['value'],
                'gain' => $point['gain'],
            ];
        }

        return $summary;
    }
}
