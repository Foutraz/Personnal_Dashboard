<?php

return [
    'client_id' => env('WITHINGS_CLIENT_ID'),
    'client_secret' => env('WITHINGS_CLIENT_SECRET'),
    'redirect_uri' => env('WITHINGS_REDIRECT_URI'),
    'endpoint' => env('WITHINGS_ENDPOINT', 'https://wbsapi.withings.net'),
    'token' => env('WITHINGS_TOKEN'),
];
