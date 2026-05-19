<?php

return [
    'default_check_in_time' => env('ATTENDANCE_CHECK_IN_TIME', '07:00'),
    'late_threshold_time' => env('ATTENDANCE_LATE_THRESHOLD', '07:15'),
    'default_check_out_time' => env('ATTENDANCE_CHECK_OUT_TIME', '14:30'),
    'check_in_end_time' => env('ATTENDANCE_CHECK_IN_END', '09:00'),
    'check_out_end_time' => env('ATTENDANCE_CHECK_OUT_END', '16:00'),

    'qr_token_secret' => env('QR_TOKEN_SECRET', ''),
    'qr_token_length' => 64,
    'qr_cache_days' => 30,
    'qr_storage_path' => 'qr-codes',

    'scanner_rate_limit' => env('SCANNER_RATE_LIMIT', 60),
];
