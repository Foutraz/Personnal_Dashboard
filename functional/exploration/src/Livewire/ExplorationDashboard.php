<?php

namespace Functional\Exploration\Livewire;

use Functional\Exploration\Actions\RebuildUserCoverage;
use Functional\Exploration\Models\ExploredCell;
use Functional\Exploration\Services\CoverageCalculator;
use Functional\Exploration\Support\BoundingBox;
use Functional\Exploration\Support\GridCell;
use Functional\Exploration\Support\PolylineDecoder;
use Functional\Sport\Models\SportActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class ExplorationDashboard extends Component
{
    /**
     * The key of the region preset whose exploration percentage is highlighted.
     */
    public string $selectedRegion = '';

    /**
     * Initialise the highlighted region to the first preset.
     */
    public function mount(): void
    {
        $regions = $this->regions();

        if ($this->selectedRegion === '' && $regions !== []) {
            $this->selectedRegion = (string) $regions[0]['key'];
        }
    }

    /**
     * Rebuild the authenticated user's coverage from its activities.
     */
    public function recalculate(RebuildUserCoverage $rebuild): void
    {
        $rebuild->handle((string) Auth::id());
    }

    /**
     * Get the configured region presets.
     *
     * @return array<int, array{key: string, label: string, min_lat: float, max_lat: float, min_lng: float, max_lng: float}>
     */
    public function regions(): array
    {
        /** @var array<int, array{key: string, label: string, min_lat: float, max_lat: float, min_lng: float, max_lng: float}> $regions */
        $regions = config('exploration.regions', []);

        return $regions;
    }

    /**
     * Load the authenticated user's explored cells.
     *
     * @return Collection<int, ExploredCell>
     */
    private function exploredCells(): Collection
    {
        return ExploredCell::query()
            ->where('user_id', Auth::id())
            ->get();
    }

    /**
     * Build the decoded trip routes payload from the user's activities.
     *
     * @return array<int, array<int, array{lat: float, lng: float}>>
     */
    private function tripRoutes(PolylineDecoder $decoder): array
    {
        return SportActivity::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('map_polyline')
            ->pluck('map_polyline')
            ->map(fn (string $polyline): array => $decoder->decode($polyline))
            ->filter(fn (array $points): bool => $points !== [])
            ->values()
            ->all();
    }

    /**
     * Render the exploration command map with coverage overlay and stat panels.
     */
    #[Layout('layouts.app')]
    #[Title('Cartes & Exploration')]
    public function render(CoverageCalculator $calculator, PolylineDecoder $decoder): View
    {
        $cells = $this->exploredCells();
        $routes = $this->tripRoutes($decoder);

        /** @var array<string, GridCell> $gridCells */
        $gridCells = $cells->mapWithKeys(function (ExploredCell $cell): array {
            $grid = GridCell::fromCoordinate($cell->lat, $cell->lng, (float) config('exploration.grid.cell_size', 0.01));

            return [$grid->key() => $grid];
        })->all();

        $cellPoints = $cells->map(fn (ExploredCell $cell): array => [
            'lat' => $cell->lat,
            'lng' => $cell->lng,
            'count' => $cell->visit_count,
        ])->values()->all();

        $totalDistance = (float) SportActivity::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('map_polyline')
            ->sum('distance');

        $areaKm2 = $calculator->coverage(collect($routes))->areaKm2;

        $regions = $this->regions();
        $regionBreakdown = collect($regions)->map(function (array $region) use ($calculator, $gridCells): array {
            return [
                'key' => $region['key'],
                'label' => $region['label'],
                'percentage' => $calculator->explorationPercentage($gridCells, BoundingBox::fromRegion($region)),
            ];
        })->all();

        $selected = collect($regionBreakdown)->firstWhere('key', $this->selectedRegion);

        return view('exploration::exploration', [
            'routes' => $routes,
            'cellPoints' => $cellPoints,
            'distinctCells' => $cells->count(),
            'totalDistanceKm' => round($totalDistance / 1000, 1),
            'areaKm2' => round($areaKm2, 1),
            'regions' => $regions,
            'regionBreakdown' => $regionBreakdown,
            'selectedPercentage' => $selected['percentage'] ?? 0.0,
        ]);
    }
}
