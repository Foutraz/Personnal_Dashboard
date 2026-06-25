<?php

namespace Functional\Exploration\Actions;

use Functional\Exploration\Models\ExploredCell;
use Functional\Exploration\Support\GridCell;
use Functional\Exploration\Support\PolylineDecoder;
use Functional\Sport\Models\SportActivity;
use Illuminate\Support\Carbon;

class RebuildUserCoverage
{
    /**
     * The grid cell size in degrees.
     */
    private float $cellSize;

    public function __construct(private PolylineDecoder $decoder)
    {
        $this->cellSize = (float) config('exploration.grid.cell_size', 0.01);
    }

    /**
     * Rebuild the explored cells of the given user from its sport activities idempotently.
     */
    public function handle(string $userId): int
    {
        /** @var array<string, array{cell: GridCell, first_seen_at: Carbon, last_seen_at: Carbon, visit_count: int}> $aggregated */
        $aggregated = [];

        SportActivity::query()
            ->where('user_id', $userId)
            ->whereNotNull('map_polyline')
            ->cursor()
            ->each(function (SportActivity $activity) use (&$aggregated): void {
                $seenAt = $activity->started_at;

                foreach ($this->decoder->decode((string) $activity->map_polyline) as $point) {
                    $cell = GridCell::fromCoordinate($point['lat'], $point['lng'], $this->cellSize);
                    $key = $cell->key();

                    if (! isset($aggregated[$key])) {
                        $aggregated[$key] = [
                            'cell' => $cell,
                            'first_seen_at' => $seenAt,
                            'last_seen_at' => $seenAt,
                            'visit_count' => 1,
                        ];

                        continue;
                    }

                    $aggregated[$key]['visit_count']++;
                    $aggregated[$key]['first_seen_at'] = $seenAt->lt($aggregated[$key]['first_seen_at']) ? $seenAt : $aggregated[$key]['first_seen_at'];
                    $aggregated[$key]['last_seen_at'] = $seenAt->gt($aggregated[$key]['last_seen_at']) ? $seenAt : $aggregated[$key]['last_seen_at'];
                }
            });

        foreach ($aggregated as $key => $entry) {
            ExploredCell::query()->updateOrCreate(
                ['user_id' => $userId, 'cell_key' => $key],
                [
                    'lat' => $entry['cell']->centerLat(),
                    'lng' => $entry['cell']->centerLng(),
                    'visit_count' => $entry['visit_count'],
                    'first_seen_at' => $entry['first_seen_at'],
                    'last_seen_at' => $entry['last_seen_at'],
                ],
            );
        }

        return count($aggregated);
    }
}
