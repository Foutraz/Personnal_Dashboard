<?php

return [
    'google' => [
        'endpoint' => env('GOOGLE_CALENDAR_BASE_URL', 'https://www.googleapis.com/calendar/v3'),
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID', env('GOOGLE_CLIENT_ID')),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET')),
        'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
    ],
    'outlook' => [
        'endpoint' => env('OUTLOOK_BASE_URL', 'https://graph.microsoft.com/v1.0'),
        'client_id' => env('OUTLOOK_CLIENT_ID'),
        'client_secret' => env('OUTLOOK_CLIENT_SECRET'),
        'redirect_uri' => env('OUTLOOK_REDIRECT_URI'),
        'tenant' => env('OUTLOOK_TENANT', 'common'),
    ],
    'sync' => [
        'window_days' => env('PLANNING_SYNC_WINDOW_DAYS', 90),
    ],
];
