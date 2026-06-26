<?php

namespace Functional\Moto\Services;

use Foutraz\Weather\Dto\CurrentWeather;
use Foutraz\Weather\Dto\ForecastEntry;
use Functional\Moto\Enums\RideRating;
use Functional\Moto\ValueObjects\RideCondition;

class MotoFriendlyScore
{
    /**
     * @var array<string, int>
     */
    private array $weights;

    /**
     * @var array<string, float|int>
     */
    private array $thresholds;

    /**
     * @var array{excellent: int, good: int, average: int, bad: int}
     */
    private array $labels;

    public function __construct()
    {
        /** @var array<string, int> $weights */
        $weights = config('moto.score.weights');
        /** @var array<string, float|int> $thresholds */
        $thresholds = config('moto.score.thresholds');
        /** @var array{excellent: int, good: int, average: int, bad: int} $labels */
        $labels = config('moto.score.labels');

        $this->weights = $weights;
        $this->thresholds = $thresholds;
        $this->labels = $labels;
    }

    /**
     * Compute the riding condition from a forecast entry.
     */
    public function forForecastEntry(ForecastEntry $entry): RideCondition
    {
        return $this->forValues(
            $entry->pop,
            $entry->rain3h !== null ? $entry->rain3h / 3.0 : 0.0,
            $entry->windSpeed,
            $entry->windGust,
            $entry->temp,
            $entry->visibility,
        );
    }

    /**
     * Compute the riding condition from a current weather reading.
     */
    public function forCurrentWeather(CurrentWeather $weather): RideCondition
    {
        return $this->forValues(
            $weather->rain1h !== null && $weather->rain1h > 0.0 ? 0.8 : 0.0,
            $weather->rain1h ?? 0.0,
            $weather->windSpeed,
            $weather->windGust,
            $weather->temp,
            $weather->visibility,
        );
    }

    /**
     * Compute the riding condition from raw weather values.
     */
    public function forValues(
        float $precipitationProbability,
        float $rainPerHour,
        float $windSpeed,
        ?float $windGust,
        float $temperature,
        ?int $visibility,
    ): RideCondition {
        $reasons = [];

        $precipitationScore = $this->precipitationScore($precipitationProbability, $rainPerHour, $reasons);
        $windScore = $this->windScore($windSpeed, $windGust, $reasons);
        $temperatureScore = $this->temperatureScore($temperature, $reasons);
        $visibilityScore = $this->visibilityScore($visibility, $reasons);

        $weighted = $precipitationScore * $this->weights['precipitation']
            + $windScore * $this->weights['wind']
            + $temperatureScore * $this->weights['temperature']
            + $visibilityScore * $this->weights['visibility'];

        $total = $this->weights['precipitation'] + $this->weights['wind'] + $this->weights['temperature'] + $this->weights['visibility'];

        $weightedScore = ($weighted / $total) * 100;

        $safetyCap = min($precipitationScore, $windScore) * 100;

        $score = (int) round(min($weightedScore, $safetyCap));

        if ($reasons === []) {
            $reasons[] = 'Conditions idéales pour rouler.';
        }

        return new RideCondition($score, RideRating::fromScore($score, $this->labels), $reasons);
    }

    /**
     * Score the precipitation component from probability and rain rate.
     *
     * @param  array<int, string>  $reasons
     */
    private function precipitationScore(float $probability, float $rainPerHour, array &$reasons): float
    {
        $probabilityScore = $this->descendingScore($probability, (float) $this->thresholds['pop_clear'], (float) $this->thresholds['pop_bad']);
        $rainScore = $this->descendingScore($rainPerHour, (float) $this->thresholds['rain_clear'], (float) $this->thresholds['rain_bad']);

        $score = min($probabilityScore, $rainScore);

        if ($rainPerHour >= (float) $this->thresholds['rain_bad']) {
            $reasons[] = 'Fortes précipitations attendues.';
        } elseif ($probability >= (float) $this->thresholds['pop_bad']) {
            $reasons[] = 'Probabilité de pluie élevée.';
        } elseif ($score < 0.7) {
            $reasons[] = 'Risque de pluie modéré.';
        }

        return $score;
    }

    /**
     * Score the wind component from sustained speed and gusts.
     *
     * @param  array<int, string>  $reasons
     */
    private function windScore(float $windSpeed, ?float $windGust, array &$reasons): float
    {
        $sustainedScore = $this->descendingScore($windSpeed, (float) $this->thresholds['wind_calm'], (float) $this->thresholds['wind_strong']);
        $gustScore = $windGust !== null
            ? $this->descendingScore($windGust, (float) $this->thresholds['wind_calm'], (float) $this->thresholds['gust_strong'])
            : 1.0;

        $score = min($sustainedScore, $gustScore);

        if ($windSpeed >= (float) $this->thresholds['wind_strong'] || ($windGust !== null && $windGust >= (float) $this->thresholds['gust_strong'])) {
            $reasons[] = 'Vent fort et rafales.';
        } elseif ($score < 0.7) {
            $reasons[] = 'Vent soutenu.';
        }

        return $score;
    }

    /**
     * Score the temperature component within the configured comfort band.
     *
     * @param  array<int, string>  $reasons
     */
    private function temperatureScore(float $temperature, array &$reasons): float
    {
        $idealMin = (float) $this->thresholds['temp_ideal_min'];
        $idealMax = (float) $this->thresholds['temp_ideal_max'];

        if ($temperature >= $idealMin && $temperature <= $idealMax) {
            return 1.0;
        }

        if ($temperature < $idealMin) {
            $score = $this->ascendingScore($temperature, (float) $this->thresholds['temp_cold'], $idealMin);

            if ($temperature <= (float) $this->thresholds['temp_cold']) {
                $reasons[] = 'Température basse, risque de gel.';
            } elseif ($score < 0.7) {
                $reasons[] = 'Température fraîche.';
            }

            return $score;
        }

        $score = $this->descendingScore($temperature, $idealMax, (float) $this->thresholds['temp_hot']);

        if ($temperature >= (float) $this->thresholds['temp_hot']) {
            $reasons[] = 'Chaleur excessive.';
        } elseif ($score < 0.7) {
            $reasons[] = 'Température élevée.';
        }

        return $score;
    }

    /**
     * Score the visibility component from the reported distance in metres.
     *
     * @param  array<int, string>  $reasons
     */
    private function visibilityScore(?int $visibility, array &$reasons): float
    {
        if ($visibility === null) {
            return 1.0;
        }

        $score = $this->ascendingScore((float) $visibility, (float) $this->thresholds['visibility_poor'], (float) $this->thresholds['visibility_clear']);

        if ($visibility <= (int) $this->thresholds['visibility_poor']) {
            $reasons[] = 'Visibilité très réduite.';
        } elseif ($score < 0.7) {
            $reasons[] = 'Visibilité dégradée.';
        }

        return $score;
    }

    /**
     * Interpolate a 0-1 score that decreases as the value rises between best and worst.
     */
    private function descendingScore(float $value, float $best, float $worst): float
    {
        if ($value <= $best) {
            return 1.0;
        }

        if ($value >= $worst) {
            return 0.0;
        }

        return 1.0 - (($value - $best) / ($worst - $best));
    }

    /**
     * Interpolate a 0-1 score that increases as the value rises between worst and best.
     */
    private function ascendingScore(float $value, float $worst, float $best): float
    {
        if ($value >= $best) {
            return 1.0;
        }

        if ($value <= $worst) {
            return 0.0;
        }

        return ($value - $worst) / ($best - $worst);
    }
}
