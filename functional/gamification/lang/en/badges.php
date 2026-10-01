<?php

return [
    'tiers' => [
        'bronze' => 'Bronze',
        'silver' => 'Silver',
        'gold' => 'Gold',
    ],
    'rules' => [
        'sport_distance' => [
            'name' => 'Sport distance',
            'description' => 'Cover :threshold km in sport activities.',
        ],
        'sport_activity_count' => [
            'name' => 'Sport activities',
            'description' => 'Log :threshold sport activities.',
        ],
        'sport_streak' => [
            'name' => 'Sport consistency',
            'description' => 'Hold a :threshold-day sport streak.',
        ],
        'health_measurement_days' => [
            'name' => 'Health tracking',
            'description' => 'Log health measurements on :threshold days.',
        ],
        'health_streak' => [
            'name' => 'Health consistency',
            'description' => 'Hold a :threshold-day health streak.',
        ],
        'finance_invested_capital' => [
            'name' => 'Invested capital',
            'description' => 'Invest :threshold € of net capital.',
        ],
        'moto_distance' => [
            'name' => 'Motorbike distance',
            'description' => 'Ride :threshold km on your motorbike.',
        ],
        'todo_tasks_completed' => [
            'name' => 'Tasks completed',
            'description' => 'Complete :threshold tasks.',
        ],
        'todo_streak' => [
            'name' => 'Task consistency',
            'description' => 'Hold a :threshold-day task streak.',
        ],
        'exploration_cells' => [
            'name' => 'Exploration',
            'description' => 'Explore :threshold cells.',
        ],
    ],
    'showcase' => [
        'title' => 'Badges',
        'counter' => ':earned / :total',
        'locked' => 'Locked',
        'pending' => 'Reached — unlocks at the next update',
        'earned' => 'Earned',
        'medal' => ':tier: :state',
        'current' => 'Current: :value',
        'next' => 'Next tier: :threshold',
        'completed' => 'Family completed',
    ],
    'units' => [
        'km' => ':value km',
        'count' => ':value',
        'days' => ':value d',
        'eur' => ':value €',
    ],
    'notification' => [
        'title' => 'New badge: :name (:tier)',
        'body' => 'You earned the :name badge (:tier) and gained :xp XP.',
    ],
];
