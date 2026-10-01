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
}
