<?php

namespace Functional\Gamification\Enums;

use Functional\Gamification\Exceptions\InvalidChallengeConfigException;
use Functional\Gamification\Exceptions\MissingChallengeTemplateConfigException;
use Functional\Gamification\Services\Dto\ChallengeTemplateSettings;
use Functional\Goals\Enums\GoalMetric;

enum ChallengeTemplateKey: string
{
    private const TEMPLATES_CONFIG_PREFIX = 'gamification.challenges.templates';

    case SportDistance = 'sport_distance';
    case SportElevation = 'sport_elevation';
    case SportActivityCount = 'sport_activity_count';
    case SportMovingTime = 'sport_moving_time';
    case MotoDistance = 'moto_distance';
    case MotoRideCount = 'moto_ride_count';
    case ExplorationCells = 'exploration_cells';

    public function metric(): GoalMetric
    {
        return match ($this) {
            self::SportDistance => GoalMetric::SportDistance,
            self::SportElevation => GoalMetric::SportElevation,
            self::SportActivityCount => GoalMetric::SportActivityCount,
            self::SportMovingTime => GoalMetric::SportMovingTime,
            self::MotoDistance => GoalMetric::MotoDistance,
            self::MotoRideCount => GoalMetric::MotoRideCount,
            self::ExplorationCells => GoalMetric::ExplorationCells,
        };
    }

    public function domain(): GamificationDomain
    {
        return match ($this) {
            self::SportDistance, self::SportElevation, self::SportActivityCount, self::SportMovingTime => GamificationDomain::Sport,
            self::MotoDistance, self::MotoRideCount => GamificationDomain::Moto,
            self::ExplorationCells => GamificationDomain::Exploration,
        };
    }

    public function unit(): ChallengeUnit
    {
        return match ($this) {
            self::SportDistance, self::MotoDistance => ChallengeUnit::Kilometers,
            self::SportElevation => ChallengeUnit::Meters,
            self::SportMovingTime => ChallengeUnit::Hours,
            self::SportActivityCount, self::MotoRideCount, self::ExplorationCells => ChallengeUnit::Count,
        };
    }

    public function configPath(): string
    {
        return self::TEMPLATES_CONFIG_PREFIX.".{$this->value}";
    }

    /**
     * @throws MissingChallengeTemplateConfigException|InvalidChallengeConfigException
     */
    public function settings(): ChallengeTemplateSettings
    {
        return ChallengeTemplateSettings::fromConfig($this);
    }

    public function label(): string
    {
        return __("gamification::challenges.templates.{$this->value}.name");
    }

    public function description(float $target): string
    {
        return trans_choice(
            "gamification::challenges.templates.{$this->value}.description",
            (int) round($target),
            ['target' => $this->unit()->format($target)],
        );
    }

    /**
     * @return list<self>
     */
    public static function forDomain(GamificationDomain $domain): array
    {
        return array_values(array_filter(self::cases(), fn (self $template): bool => $template->domain() === $domain));
    }

    /**
     * @param  list<self>  $eligible
     */
    public static function pickFor(array $eligible, int $isoWeek): ?self
    {
        if ($eligible === []) {
            return null;
        }

        return $eligible[$isoWeek % count($eligible)];
    }
}
