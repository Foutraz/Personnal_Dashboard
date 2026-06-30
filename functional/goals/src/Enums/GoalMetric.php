<?php

namespace Functional\Goals\Enums;

enum GoalMetric: string
{
    case SportDistance = 'sport_distance';
    case SportElevation = 'sport_elevation';
    case SportActivityCount = 'sport_activity_count';
    case SportMovingTime = 'sport_moving_time';
    case FinanceInvestedCapital = 'finance_invested_capital';
    case FinancePortfolioValue = 'finance_portfolio_value';
    case Manual = 'manual';
    case MotoDistance = 'moto_distance';
    case MotoRideCount = 'moto_ride_count';
    case ExplorationCells = 'exploration_cells';
    case TodoCompletionRate = 'todo_completion_rate';

    /**
     * Get the human-readable label of the goal metric.
     */
    public function label(): string
    {
        return match ($this) {
            self::SportDistance => 'Distance parcourue',
            self::SportElevation => 'Dénivelé positif',
            self::SportActivityCount => 'Nombre de sorties',
            self::SportMovingTime => 'Temps en mouvement',
            self::FinanceInvestedCapital => 'Capital investi',
            self::FinancePortfolioValue => 'Valeur du portefeuille',
            self::Manual => 'Suivi manuel',
            self::MotoDistance => 'Distance moto',
            self::MotoRideCount => 'Sorties moto',
            self::ExplorationCells => 'Cellules explorées',
            self::TodoCompletionRate => 'Taux de complétion',
        };
    }

    /**
     * Get the canonical unit suggested for the goal metric.
     */
    public function defaultUnit(): string
    {
        return match ($this) {
            self::SportDistance => 'km',
            self::SportElevation => 'm',
            self::SportActivityCount => 'sorties',
            self::SportMovingTime => 'h',
            self::FinanceInvestedCapital, self::FinancePortfolioValue => '€',
            self::Manual => '',
            self::MotoDistance => 'km',
            self::MotoRideCount => 'sorties',
            self::ExplorationCells => 'cellules',
            self::TodoCompletionRate => '%',
        };
    }

    /**
     * Get the goal type that owns the metric.
     */
    public function type(): GoalType
    {
        return match ($this) {
            self::SportDistance, self::SportElevation, self::SportActivityCount, self::SportMovingTime => GoalType::Sport,
            self::FinanceInvestedCapital, self::FinancePortfolioValue => GoalType::Finance,
            self::Manual => GoalType::Personal,
            self::MotoDistance, self::MotoRideCount => GoalType::Moto,
            self::ExplorationCells => GoalType::Exploration,
            self::TodoCompletionRate => GoalType::Productivity,
        };
    }

    /**
     * Determine whether the metric is computed automatically from another module.
     */
    public function isAutomatic(): bool
    {
        return $this !== self::Manual;
    }
}
