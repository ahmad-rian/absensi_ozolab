<?php

return [
    // Telegram Bot API base URL.
    'api_base' => env('TELEGRAM_API_BASE', 'https://api.telegram.org'),

    'timeout' => env('TELEGRAM_TIMEOUT', 10),
    'queue' => env('TELEGRAM_QUEUE', 'whatsapp'),
];
