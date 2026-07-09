<?php

return [
    // Ozolab WA Gateway (default fallback)
    'base_url' => env('WA_BASE_URL', 'https://wa.ozolab.id'),
    'api_key' => env('WA_API_KEY', ''),
    'sender' => env('WA_SENDER', ''),

    'timeout' => env('WA_TIMEOUT', 10),
    'queue' => env('WA_QUEUE', 'default'),
    'retry' => [
        'times' => 3,
        'backoff' => [30, 120, 600],
    ],
];
