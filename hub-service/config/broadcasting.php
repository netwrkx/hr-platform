<?php

return [

    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY', 'hr-platform-key'),
            'secret' => env('PUSHER_APP_SECRET', 'hr-platform-secret'),
            'app_id' => env('PUSHER_APP_ID', 'hr-platform'),
            'options' => [
                'host' => env('PUSHER_HOST', 'soketi'),
                'port' => env('PUSHER_PORT', 6001),
                'scheme' => env('PUSHER_SCHEME', 'http'),
                'encrypted' => false,
                'useTLS' => false,
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
