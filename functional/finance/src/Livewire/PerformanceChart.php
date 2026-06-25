<?php

namespace Functional\Finance\Livewire;

use Functional\Finance\Models\Position;
use Functional\Finance\Services\PerformanceCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class PerformanceChart extends Component
{
    /**
     * Refresh the chart when the portfolio reports a change.
     */
    #[On('portfolio-updated')]
    public function refresh(): void
    {
        //
    }

    /**
     * Load the authenticated user's priced positions.
     *
     * @return Collection<int, Position>
     */
    public function positions(): Collection
    {
        return Position::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('current_price')
            ->orderByDesc('quantity')
            ->get();
    }

    /**
     * Render the value-versus-invested bars and the allocation donut chart.
     */
    public function render(PerformanceCalculator $performance): View
    {
        $positions = $this->positions();

        $labels = [];
        $invested = [];
        $values = [];

        foreach ($positions as $position) {
            $labels[] = $position->asset_symbol;
            $invested[] = $performance->positionNetInvested($position);
            $values[] = $performance->positionValue($position);
        }

        $allocation = $performance->allocationWeights($positions);

        return view('finance::livewire.performance-chart', [
            'labels' => $labels,
            'invested' => $invested,
            'values' => $values,
            'allocationLabels' => array_keys($allocation),
            'allocationValues' => array_values($allocation),
        ]);
    }
}
