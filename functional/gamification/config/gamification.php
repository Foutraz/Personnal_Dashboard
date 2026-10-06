<?php

return [
    'timezone' => env('GAMIFICATION_TIMEZONE', 'Europe/Paris'),
    'level_curve' => [
        'base' => env('GAMIFICATION_LEVEL_BASE', 250),
        'exponent' => env('GAMIFICATION_LEVEL_EXPONENT', 1.5),
    ],
    'xp' => [
        'sport' => [
            'activity_base' => 10,
            'distance_per_km' => 1,
            'distance_cap' => 30,
            'elevation_per_100m' => 1,
            'elevation_cap' => 20,
        ],
        'todo' => [
            'task_completed' => 3,
            'daily_task_cap' => 10,
        ],
        'exploration' => [
            'cell_discovered' => 2,
            'daily_cap' => 40,
        ],
        'health' => [
            'measurement_day' => 5,
        ],
        'moto' => [
            'ride_base' => 10,
            'distance_per_20km' => 1,
            'distance_cap' => 25,
        ],
        'finance' => [
            'positive_savings_month' => 20,
            'investment_contribution_month' => 10,
            'investment_minimum_net_bought' => 10,
        ],
    ],
    'streaks' => [
        'milestones' => [
            7 => 25,
            30 => 100,
            100 => 400,
        ],
    ],
    'badges' => [
        'tier_xp' => [
            'bronze' => 50,
            'silver' => 150,
            'gold' => 500,
        ],
        'thresholds' => [
            'sport_distance' => ['bronze' => 100, 'silver' => 1000, 'gold' => 5000],
            'sport_activity_count' => ['bronze' => 10, 'silver' => 100, 'gold' => 500],
            'sport_streak' => ['bronze' => 7, 'silver' => 30, 'gold' => 100],
            'health_measurement_days' => ['bronze' => 7, 'silver' => 60, 'gold' => 365],
            'health_streak' => ['bronze' => 7, 'silver' => 30, 'gold' => 100],
            'finance_invested_capital' => ['bronze' => 1000, 'silver' => 10000, 'gold' => 50000],
            'moto_distance' => ['bronze' => 500, 'silver' => 5000, 'gold' => 20000],
            'todo_tasks_completed' => ['bronze' => 25, 'silver' => 250, 'gold' => 1000],
            'todo_streak' => ['bronze' => 7, 'silver' => 30, 'gold' => 100],
            'exploration_cells' => ['bronze' => 100, 'silver' => 1000, 'gold' => 5000],
        ],
    ],
    'challenges' => [
        'history_weeks' => 4,
        'min_active_weeks' => 2,
        'stretch_ratio' => 0.10,
        'closing_grace_hours' => 48,
        'xp_reward' => 50,
        'templates' => [
            'sport_distance' => ['step' => 1, 'floor' => 5, 'cap' => 300],
            'sport_elevation' => ['step' => 50, 'floor' => 100, 'cap' => 10000],
            'sport_activity_count' => ['step' => 1, 'floor' => 1, 'cap' => 14],
            'sport_moving_time' => ['step' => 0.5, 'floor' => 1, 'cap' => 30],
            'moto_distance' => ['step' => 10, 'floor' => 50, 'cap' => 3000],
            'moto_ride_count' => ['step' => 1, 'floor' => 1, 'cap' => 14],
            'exploration_cells' => ['step' => 5, 'floor' => 10, 'cap' => 1000],
        ],
    ],
];
