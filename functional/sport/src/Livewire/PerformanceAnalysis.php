<?php

namespace Functional\Sport\Livewire;

use Functional\Sport\Models\SportActivity;
use Functional\Sport\Services\SportStatisticsCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PerformanceAnalysis extends Component
{
    /**
     * Load the authenticated user's recent activities exposing heart rate.
     *
     * @return Collection<int, SportActivity>
     */
    public function activities(): Collection
    {
        return SportActivity::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('average_heartrate')
            ->latest('started_at')
            ->limit(20)
            ->get();
    }

    /**
     * Render the pace and heart-rate analysis chart.
     */
    public function render(SportStatisticsCalculator $calculator): View
    {
        $activities = $this->activities();
        $chronological = $activities->sortBy(fn (SportActivity $activity): int => $activity->started_at->getTimestamp())->values();

        return view('sport::livewire.performance-analysis', [
            'averagePace' => $calculator->averagePace($activities),
            'labels' => $chronological->map(fn (SportActivity $activity): string => $activity->started_at->format('d/m'))->all(),
            'heartRates' => $chronological->map(fn (SportActivity $activity): float => round($activity->average_heartrate ?? 0.0, 0))->all(),
        ]);
    }
}
