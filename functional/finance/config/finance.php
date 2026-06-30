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
    'marketdata' => [
        'endpoint' => env('COINGECKO_ENDPOINT', 'https://api.coingecko.com'),
        'api_key' => env('COINGECKO_API_KEY'),
        'cache_ttl' => env('FINANCE_MARKETDATA_CACHE_TTL', 900),
        'coingecko_ids' => [
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
        ],
    ],
    'gocardless' => [
        'secret_id' => env('GOCARDLESS_SECRET_ID'),
        'secret_key' => env('GOCARDLESS_SECRET_KEY'),
        'redirect_uri' => env('GOCARDLESS_REDIRECT_URI'),
        'endpoint' => env('GOCARDLESS_ENDPOINT', 'https://bankaccountdata.gocardless.com'),
        'institution_id' => env('GOCARDLESS_INSTITUTION_ID', 'SANDBOXFINANCE_SFIN0000'),
    ],
];
