<?php

namespace Functional\Gamification\Enums;

enum BadgeRuleKey: string
{
    private const THRESHOLDS_CONFIG_PREFIX = 'gamification.badges.thresholds';

    case SportDistance = 'sport_distance';
    case SportActivityCount = 'sport_activity_count';
    case SportStreak = 'sport_streak';
    case HealthMeasurementDays = 'health_measurement_days';
    case HealthStreak = 'health_streak';
    case FinanceInvestedCapital = 'finance_invested_capital';
    case MotoDistance = 'moto_distance';
    case TodoTasksCompleted = 'todo_tasks_completed';
    case TodoStreak = 'todo_streak';
    case ExplorationCells = 'exploration_cells';

    /**
     * Get the translated name of the badge family.
     */
    public function label(): string
    {
        return __("gamification::badges.rules.{$this->value}.name");
    }

    /**
     * Get the translated description of the badge family with its already formatted threshold.
     */
    public function description(string $threshold): string
    {
        return __("gamification::badges.rules.{$this->value}.description", ['threshold' => $threshold]);
    }

    /**
     * Get the config path holding the thresholds of every tier of the family.
     */
    public function thresholdsConfigPath(): string
    {
        return self::THRESHOLDS_CONFIG_PREFIX.".{$this->value}";
    }

    /**
     * Get the config path holding the threshold of one tier of the family.
     */
    public function thresholdConfigPath(BadgeTier $tier): string
    {
        return "{$this->thresholdsConfigPath()}.{$tier->value}";
    }

    /**
     * Get the unique catalogue key of the badge of one tier of the family.
     */
    public function badgeKey(BadgeTier $tier): string
    {
        return "{$this->value}_{$tier->value}";
    }
}
