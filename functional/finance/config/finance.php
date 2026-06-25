<?php

return [
    'projection' => [
        'default_annual_rate' => env('FINANCE_PROJECTION_RATE', 7.0),
        'default_years' => env('FINANCE_PROJECTION_YEARS', 10),
    ],
    'dca' => [
        'default_amount' => env('FINANCE_DCA_AMOUNT', 200.0),
        'default_annual_rate' => env('FINANCE_DCA_RATE', 7.0),
        'default_years' => env('FINANCE_DCA_YEARS', 10),
    ],
];
