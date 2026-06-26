<?php

namespace Functional\Moto\Services;

use DateTimeImmutable;
use Foutraz\Weather\Dto\Forecast;
use Foutraz\Weather\Dto\ForecastEntry;
use Functional\Moto\Enums\RideRating;
use Functional\Moto\ValueObjects\FavorableSlot;

class FavorableSlotFinder
{
    public function __construct(public MotoFriendlyScore $motoFriendlyScore) {}

    /**
     * Find the best contiguous upcoming windows whose entries all meet the minimum score.
     *
     * @return array<int, FavorableSlot>
     */
    public function find(Forecast $forecast): array
    {
        $minimumScore = (int) config('moto.slots.minimum_score');
        $maximum = (int) config('moto.slots.maximum');

        $slots = [];
        $current = null;

        foreach ($forecast->entries as $entry) {
            $condition = $this->motoFriendlyScore->forForecastEntry($entry);

            if ($condition->score >= $minimumScore) {
                $current = $this->extendWindow($current, $entry, $condition->score);

                continue;
            }

            if ($current !== null) {
                $slots[] = $this->buildSlot($current);
                $current = null;
            }
        }

        if ($current !== null) {
            $slots[] = $this->buildSlot($current);
        }

        usort($slots, fn (FavorableSlot $first, FavorableSlot $second): int => $second->score <=> $first->score);

        return array_slice($slots, 0, $maximum);
    }

    /**
     * Extend the current open window with the given entry.
     *
     * @param  array{start: DateTimeImmutable, end: DateTimeImmutable, scores: array<int, int>}|null  $current
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable, scores: array<int, int>}
     */
    private function extendWindow(?array $current, ForecastEntry $entry, int $score): array
    {
        $end = $entry->dt->modify('+3 hours');

        if ($current === null) {
            return ['start' => $entry->dt, 'end' => $end, 'scores' => [$score]];
        }

        $current['end'] = $end;
        $current['scores'][] = $score;

        return $current;
    }

    /**
     * Build a favorable slot from an accumulated window.
     *
     * @param  array{start: DateTimeImmutable, end: DateTimeImmutable, scores: array<int, int>}  $window
     */
    private function buildSlot(array $window): FavorableSlot
    {
        $average = (int) round(array_sum($window['scores']) / count($window['scores']));

        /** @var array{excellent: int, good: int, average: int, bad: int} $labels */
        $labels = config('moto.score.labels');

        return new FavorableSlot(
            $window['start'],
            $window['end'],
            $average,
            RideRating::fromScore($average, $labels),
        );
    }
}
