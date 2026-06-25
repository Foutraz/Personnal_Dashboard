<?php

namespace Functional\Sport\Livewire;

use Functional\Sport\Enums\EvolutionPeriod;
use Functional\Sport\Models\SportActivity;
use Functional\Sport\Services\SportStatisticsCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SportStatistics extends Component
{
    /**
     * The active evolution period value.
     */
    public string $period = 'monthly';

    /**
     * Load the authenticated user's activities.
     *
     * @return Collection<int, SportActivity>
     */
    public function activities(): Collection
    {
        return SportActivity::query()
            ->where('user_id', Auth::id())
            ->orderBy('started_at')
            ->get();
    }

    /**
     * Render the statistics tiles and the evolution area chart.
     */
    public function render(SportStatisticsCalculator $calculator): View
    {
        $activities = $this->activities();
        $period = EvolutionPeriod::tryFrom($this->period) ?? EvolutionPeriod::Monthly;
        $evolution = $calculator->evolutionByPeriod($activities, $period);

        return view('sport::livewire.sport-statistics', [
            'totalDistance' => $calculator->totalDistance($activities),
            'totalElevation' => $calculator->totalElevation($activities),
            'totalMovingTime' => $calculator->totalMovingTime($activities),
            'count' => $activities->count(),
            'periods' => EvolutionPeriod::cases(),
            'evolutionLabels' => array_keys($evolution),
            'evolutionValues' => array_map(fn (float $meters): float => round($meters / 1000, 1), array_values($evolution)),
        ]);
    }
}
