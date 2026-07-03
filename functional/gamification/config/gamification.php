<?php

return [
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
        ],
    ],
];
