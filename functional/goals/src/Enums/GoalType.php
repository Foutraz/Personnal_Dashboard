<?php

namespace Functional\Goals\Enums;

enum GoalType: string
{
    case Sport = 'sport';
    case Finance = 'finance';
    case Personal = 'personal';

    /**
     * Get the human-readable label of the goal type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sport => 'Sport',
            self::Finance => 'Finance',
            self::Personal => 'Personnel',
        };
    }

    /**
     * Get the neon accent color associated with the goal type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Sport => 'cyan',
            self::Finance => 'lime',
            self::Personal => 'violet',
        };
    }

    /**
     * Get the metrics that are valid for the goal type.
     *
     * @return array<int, GoalMetric>
     */
    public function metrics(): array
    {
        return match ($this) {
            self::Sport => [
                GoalMetric::SportDistance,
                GoalMetric::SportElevation,
                GoalMetric::SportActivityCount,
                GoalMetric::SportMovingTime,
            ],
            self::Finance => [
                GoalMetric::FinanceInvestedCapital,
                GoalMetric::FinancePortfolioValue,
            ],
            self::Personal => [
                GoalMetric::Manual,
            ],
        };
    }
}
