<?php

return [
    'tiers' => [
        'bronze' => 'Bronze',
        'silver' => 'Argent',
        'gold' => 'Or',
    ],
    'rules' => [
        'sport_distance' => [
            'name' => 'Distance sportive',
            'description' => 'Cumulez :threshold km en activité sportive.',
        ],
        'sport_activity_count' => [
            'name' => 'Assiduité sportive',
            'description' => 'Enregistrez :threshold activités sportives.',
        ],
        'sport_streak' => [
            'name' => 'Régularité sportive',
            'description' => 'Tenez une série sportive de :threshold jours.',
        ],
        'health_measurement_days' => [
            'name' => 'Suivi santé',
            'description' => 'Mesurez votre santé pendant :threshold jours.',
        ],
        'health_streak' => [
            'name' => 'Régularité santé',
            'description' => 'Tenez une série santé de :threshold jours.',
        ],
        'finance_invested_capital' => [
            'name' => 'Capital investi',
            'description' => 'Investissez :threshold € de capital net.',
        ],
        'moto_distance' => [
            'name' => 'Distance moto',
            'description' => 'Parcourez :threshold km à moto.',
        ],
        'todo_tasks_completed' => [
            'name' => 'Tâches terminées',
            'description' => 'Terminez :threshold tâches.',
        ],
        'todo_streak' => [
            'name' => 'Régularité tâches',
            'description' => 'Tenez une série de tâches de :threshold jours.',
        ],
        'exploration_cells' => [
            'name' => 'Exploration',
            'description' => 'Explorez :threshold cellules.',
        ],
    ],
    'showcase' => [
        'title' => 'Badges',
        'counter' => ':earned / :total',
        'locked' => 'Verrouillé',
        'pending' => 'Atteint — débloqué à la prochaine mise à jour',
        'earned' => 'Obtenu',
        'medal' => ':tier : :state',
        'current' => 'Actuel : :value',
        'next' => 'Prochain palier : :threshold',
        'completed' => 'Famille complétée',
    ],
    'units' => [
        'km' => ':value km',
        'count' => ':value',
        'days' => ':value j',
        'eur' => ':value €',
    ],
    'notification' => [
        'title' => 'Nouveau badge : :name (:tier)',
        'body' => 'Vous avez obtenu le badge :name (:tier) et gagné :xp XP.',
    ],
    'dashboard' => [
        'line' => 'Badges : :earned / :total',
    ],
];
