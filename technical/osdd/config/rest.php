<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rest Gates
    |--------------------------------------------------------------------------
    |
    | The following configuration option contains gates customisation. You might
    | want to adapt this feature to your needs.
    |
    */

    'gates' => [
        'enabled' => true,
        'key'     => 'gates',
        'message' => [
            'enabled' => false,
        ],
        // Here you can customize the keys for each gate
        'names' => [
            'authorized_to_view'         => 'authorized_to_view',
            'authorized_to_create'       => 'authorized_to_create',
            'authorized_to_update'       => 'authorized_to_update',
            'authorized_to_delete'       => 'authorized_to_delete',
            'authorized_to_restore'      => 'authorized_to_restore',
            'authorized_to_force_delete' => 'authorized_to_force_delete',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rest Authorizations
    |--------------------------------------------------------------------------
    |
    | This is the feature that automatically binds to policies to validate incoming requests.
    | Laravel Rest Api will validate each models searched / mutated / deleted to avoid leaks in your API.
    |
    */

    'authorizations' => [
        'enabled' => true,
        'cache'   => [
            'enabled' => true,
            'default' => 5, // Cache minutes by default
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Precognition Support
    |--------------------------------------------------------------------------
    |
    | This option enables support for Laravel Precognition, which allows
    | frontend applications to perform validation requests without executing
    | controller logic. When enabled, requests containing the "Precognition"
    | header will only trigger middleware and validation, skipping the actual
    | controller method. This is especially useful for live form validation.
    |
    */

    'precognition' => [
        'enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rest Documentation
    |--------------------------------------------------------------------------
    |
    | This is the feature that generates automatically your API documentation for you.
    | Laravel Rest Api will validate each models searched / mutated / deleted to avoid leaks in your API.
    | This feature is based on OpenApi, for more detail see: https://swagger.io/specification/
    |
    */

    'documentation' => [
        'routing' => [
            'enabled'     => true,
            'domain'      => null,
            'path'        => '/api-documentation',
            'middlewares' => [
                'api',
            ],
        ],
        'info' => [
            'title'          => config('app.name'),
            'summary'        => 'This is my project\'s documentation',
            'description'    => 'Find out all about my project\'s API',
            'termsOfService' => null, // (Optional) Url to terms of services
            'contact'        => [
                'name'  => 'Quentin Mari',
                'email' => 'quentin.mari0409@gmail.com',
            ],
            'license' => [
                'url'        => null,
                'name'       => 'MIT',
                'identifier' => 'MIT',
            ],
            'version' => '1.0.0',
        ],
        // See https://spec.openapis.org/oas/v3.1.0#server-object
        'servers' => [
            [
                'url'         => '/', // Relative to current
                'description' => 'The current server',
            ],
            //  [
            //      'url' => '"https://my-server.com:{port}/{basePath}"',
            //      'description' => 'Production server',
            //      'variables' => [
            //          'port' => [
            //              'enum' => ['80', '443'],
            //              'default' => '443'
            //           ],
            //           'basePath' => [
            //              'default' => 'v2',
            //              'enum' => ['v1', 'v2'],
            //           ]
            //       ]
            //  ]
        ],
        // See https://spec.openapis.org/oas/v3.1.0#security-scheme-object
        'security' => [
            //            [
            //                "api_key" => []
            //            ],
            //            [
            //                "auth" => [
            //                    'write:users',
            //                    'read:users'
            //                ]
            //            ]
        ],
        // See https://spec.openapis.org/oas/v3.1.0#security-scheme-object
        'securitySchemes' => [
            "http_bearer" => [
                "description" => "HTTP authentication with bearer token",
                "type" => "http",
                "scheme" => "bearer",
                "bearerFormat" => "JWT"
            ]
        ],
    ],
];
