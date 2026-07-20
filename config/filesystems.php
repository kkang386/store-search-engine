<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'serve'  => true,
            'throw'  => false,
            'report' => false,
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => rtrim((string) env('APP_URL'), '/') . '/storage',
            'visibility' => 'public',
            'throw'      => false,
            'report'     => false,
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION'),
            'bucket'                  => env('AWS_BUCKET'),
            'url'                     => env('AWS_URL'),
            'endpoint'                => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
            'report'                  => false,
        ],

        // CSV import files. Set IMPORTS_DISK=s3 in production so files are shared
        // across app and worker containers and survive ECS task restarts.
        'imports' => env('IMPORTS_DISK', 'local') === 's3'
            ? [
                'driver'                  => 's3',
                'region'                  => env('AWS_DEFAULT_REGION', 'us-west-2'),
                'bucket'                  => env('AWS_IMPORTS_BUCKET'),
                'use_path_style_endpoint' => false,
                'throw'                   => false,
                'report'                  => false,
            ]
            : [
                'driver' => 'local',
                'root'   => storage_path('app/private'),
                'serve'  => false,
                'throw'  => false,
                'report' => false,
            ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
