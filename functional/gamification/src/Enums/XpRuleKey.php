<?php

namespace Functional\Gamification\Enums;

enum XpRuleKey: string
{
    case SportActivity = 'sport_activity';
    case HealthMeasurementDay = 'health_measurement_day';
    case FinanceMonth = 'finance_month';
    case MotoRide = 'moto_ride';
    case TodoTaskCompleted = 'todo_task_completed';
    case ExplorationDailyCells = 'exploration_daily_cells';
    case StreakMilestone = 'streak_milestone';
    case BadgeAward = 'badge_award';

    public function isBonus(): bool
    {
        return match ($this) {
            self::StreakMilestone, self::BadgeAward => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function bonusKeys(): array
    {
        return array_values(array_map(
            fn (self $key): string => $key->value,
            array_filter(self::cases(), fn (self $key): bool => $key->isBonus()),
        ));
    }
}
