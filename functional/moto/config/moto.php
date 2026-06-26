<?php

return [
    'location' => [
        'lat' => env('MOTO_DEFAULT_LAT', 48.8566),
        'lon' => env('MOTO_DEFAULT_LON', 2.3522),
        'label' => env('MOTO_DEFAULT_LOCATION_LABEL', 'Paris'),
    ],
    'forecast' => [
        'cache_ttl' => env('MOTO_FORECAST_CACHE_TTL', 1800),
    ],
    'score' => [
        'weights' => [
            'precipitation' => 35,
            'wind' => 25,
            'temperature' => 25,
            'visibility' => 15,
        ],
        'thresholds' => [
            'pop_clear' => 0.10,
            'pop_bad' => 0.60,
            'rain_clear' => 0.0,
            'rain_bad' => 4.0,
            'wind_calm' => 4.0,
            'wind_strong' => 14.0,
            'gust_strong' => 18.0,
            'temp_ideal_min' => 15.0,
            'temp_ideal_max' => 28.0,
            'temp_cold' => 3.0,
            'temp_hot' => 38.0,
            'visibility_clear' => 10000,
            'visibility_poor' => 2000,
        ],
        'labels' => [
            'excellent' => 80,
            'good' => 65,
            'average' => 45,
            'bad' => 25,
        ],
    ],
    'slots' => [
        'minimum_score' => env('MOTO_SLOT_MINIMUM_SCORE', 65),
        'maximum' => env('MOTO_SLOT_MAXIMUM', 4),
    ],
];
