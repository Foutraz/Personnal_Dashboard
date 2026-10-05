<?php

return [
    'layers' => [
        'paths' => [
            'functional' => base_path('functional'),
            'technical' => base_path('technical'),
        ],
    ],

    'rest' => [
        'max_mutate_operations' => 100,
        'requests_per_minute' => 120,
    ],
];
