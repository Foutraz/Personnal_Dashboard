<?php

return [
    'grid' => [
        'cell_size' => env('EXPLORATION_CELL_SIZE', 0.01),
    ],
    'regions' => [
        [
            'key' => 'ile-de-france',
            'label' => 'Île-de-France',
            'min_lat' => 48.12,
            'max_lat' => 49.24,
            'min_lng' => 1.45,
            'max_lng' => 3.56,
        ],
        [
            'key' => 'auvergne-rhone-alpes',
            'label' => 'Auvergne-Rhône-Alpes',
            'min_lat' => 44.12,
            'max_lat' => 46.80,
            'min_lng' => 2.06,
            'max_lng' => 7.18,
        ],
        [
            'key' => 'provence-alpes-cote-d-azur',
            'label' => 'Provence-Alpes-Côte d\'Azur',
            'min_lat' => 43.00,
            'max_lat' => 45.13,
            'min_lng' => 4.23,
            'max_lng' => 7.72,
        ],
    ],
];
