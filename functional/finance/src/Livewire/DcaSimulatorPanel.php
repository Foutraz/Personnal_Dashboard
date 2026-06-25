<?php

namespace Functional\Finance\Livewire;

use Functional\Finance\Enums\DcaFrequency;
use Functional\Finance\Services\DcaSimulator;
use Functional\Finance\Services\Dto\DcaParameters;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DcaSimulatorPanel extends Component
{
    /**
     * The periodic contribution amount of the simulation.
     */
    public float $periodicAmount = 200.0;

    /**
     * The contribution frequency value of the simulation.
     */
    public string $frequency = 'monthly';

    /**
     * The duration in years of the simulation.
     */
    public int $years = 10;

    /**
     * The assumed annual return rate of the simulation.
     */
    public float $annualReturnRate = 7.0;

    /**
     * Mount the panel with the configured defaults.
     */
    public function mount(): void
    {
        $this->periodicAmount = (float) config('finance.dca.default_amount', 200.0);
        $this->annualReturnRate = (float) config('finance.dca.default_annual_rate', 7.0);
        $this->years = (int) config('finance.dca.default_years', 10);
    }

    /**
     * Render the interactive DCA simulator with its live projected curve.
     */
    public function render(DcaSimulator $simulator): View
    {
        $frequency = DcaFrequency::tryFrom($this->frequency) ?? DcaFrequency::Monthly;
        $parameters = DcaParameters::create($this->periodicAmount, $frequency, max($this->years, 1), $this->annualReturnRate);

        $summary = $simulator->yearlySummary($parameters);
        $final = end($summary);

        return view('finance::livewire.dca-simulator-panel', [
            'frequencies' => DcaFrequency::cases(),
            'labels' => array_map(fn (array $point): string => 'An '.$point['year'], $summary),
            'investedSeries' => array_map(fn (array $point): float => $point['invested'], $summary),
            'valueSeries' => array_map(fn (array $point): float => $point['value'], $summary),
            'finalInvested' => $final !== false ? $final['invested'] : 0.0,
            'finalValue' => $final !== false ? $final['value'] : 0.0,
            'finalGain' => $final !== false ? $final['gain'] : 0.0,
        ]);
    }
}
