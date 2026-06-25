<?php

namespace Functional\Finance\Livewire;

use Functional\Finance\Models\Position;
use Functional\Finance\Services\Dto\ProjectionParameters;
use Functional\Finance\Services\PerformanceCalculator;
use Functional\Finance\Services\ProjectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class ProjectionsPanel extends Component
{
    /**
     * The ongoing monthly contribution applied to the projection.
     */
    public float $monthlyContribution = 200.0;

    /**
     * The duration in years of the projection.
     */
    public int $years = 10;

    /**
     * The assumed annual return rate of the projection.
     */
    public float $annualReturnRate = 7.0;

    /**
     * Mount the panel with the configured defaults.
     */
    public function mount(): void
    {
        $this->annualReturnRate = (float) config('finance.projection.default_annual_rate', 7.0);
        $this->years = (int) config('finance.projection.default_years', 10);
    }

    /**
     * Refresh the projection when the portfolio reports a change.
     */
    #[On('portfolio-updated')]
    public function refresh(): void
    {
        //
    }

    /**
     * Compute the current market value of the authenticated user's portfolio.
     */
    public function startingValue(PerformanceCalculator $performance): float
    {
        $positions = Position::query()
            ->where('user_id', Auth::id())
            ->get();

        return $performance->globalPerformance($positions)->currentValue;
    }

    /**
     * Render the forward projection of the current portfolio.
     */
    public function render(PerformanceCalculator $performance, ProjectionService $projection): View
    {
        $startingValue = $this->startingValue($performance);
        $parameters = ProjectionParameters::create($startingValue, $this->monthlyContribution, max($this->years, 1), $this->annualReturnRate);

        $points = $projection->project($parameters);
        $final = end($points);

        return view('finance::livewire.projections-panel', [
            'startingValue' => $startingValue,
            'labels' => array_map(fn (array $point): string => (string) (now()->year + $point['year']), $points),
            'contributedSeries' => array_map(fn (array $point): float => $point['contributed'], $points),
            'valueSeries' => array_map(fn (array $point): float => $point['value'], $points),
            'finalValue' => $final !== false ? $final['value'] : 0.0,
            'finalGain' => $final !== false ? $final['gain'] : 0.0,
        ]);
    }
}
