<?php

return [
    'enabled' => (bool) env('LEAD_DELIVERY_ENABLED', false),

    'driver' => env('LEAD_DELIVERY_DRIVER', 'null') ?? 'null',

    'timeout' => (int) env('LEAD_DELIVERY_TIMEOUT', 10),

    'max_attempts' => (int) env('LEAD_DELIVERY_MAX_ATTEMPTS', 3),

    'local_csv' => [
        'disk' => env(
            'LEAD_DELIVERY_LOCAL_CSV_DISK',
            'local'
        ),

        'directory' => env(
            'LEAD_DELIVERY_LOCAL_CSV_DIRECTORY',
            'lead-delivery/outbox'
        ),
    ],
];
