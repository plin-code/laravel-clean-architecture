<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Clean Architecture Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the Laravel Clean
    | Architecture package.
    |
    */

    'default_namespace' => 'App',

    'directories' => [
        'domain'         => 'app/Domain',
        'application'    => 'app/Application',
        'infrastructure' => 'app/Infrastructure',
    ],

    'stubs' => [
        'path' => base_path('stubs/clean-architecture'),
    ],

    'auto_discovery' => [
        'enabled' => true,
        'paths'   => [
            'app/Application/Actions',
            'app/Application/Services',
            'app/Domain',
        ],
    ],

    'validation' => [
        'strict_mode'     => false,
        'custom_messages' => true,
    ],

    'logging' => [
        'enabled' => true,
        'channel' => 'daily',
    ],
];
