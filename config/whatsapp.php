<?php

return [
    'base_url' => env('WHATSAPP_BASE_URL', ''),
    'api_key' => env('WHATSAPP_API_KEY', ''),
    'sender_id' => env('WHATSAPP_SENDER_ID', ''),
    'timeout' => env('WHATSAPP_TIMEOUT', 10),
    'attendance_template' => env('WHATSAPP_ATTENDANCE_TEMPLATE', 'attendance_notify_v1'),
    'queue' => env('WHATSAPP_QUEUE', 'whatsapp'),
    'retry' => [
        'times' => 3,
        'backoff' => [30, 120, 600],
    ],
];
