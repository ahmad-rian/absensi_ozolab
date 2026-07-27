<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Zona Waktu Operasional
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh App\Support\SchoolTime. Terpisah dari `app.timezone` (UTC)
    | supaya timestamp lama tidak bergeser.
    |
    */
    'timezone' => env('ATTENDANCE_TIMEZONE', 'Asia/Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | Jendela Absensi Default
    |--------------------------------------------------------------------------
    |
    | Nilai awal saat membuat baris `attendance_schedules`. Setelah dibuat,
    | tiap sekolah bebas menyesuaikan lewat menu Jadwal Absensi.
    |
    | 06:00 ── HADIR ── 08:00 ── TERLAMBAT ── 13:00 ── PULANG ── 18:00 ── auto out
    |
    */
    'default_schedule' => [
        'check_in_start' => env('ATTENDANCE_CHECK_IN_START', '06:00'),
        'check_in_end' => env('ATTENDANCE_CHECK_IN_END', '08:00'),
        'late_threshold' => env('ATTENDANCE_LATE_THRESHOLD', '08:00'),
        'check_out_start' => env('ATTENDANCE_CHECK_OUT_START', '13:00'),
        'check_out_end' => env('ATTENDANCE_CHECK_OUT_END', '18:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Absen Sholat Dzuhur
    |--------------------------------------------------------------------------
    |
    | Nilai awal; tiap sekolah menimpanya lewat `schools.settings`
    | (prayer_enabled, prayer_start, prayer_end, prayer_all_religions).
    | Hari aktif mengikuti `attendance_schedules`, bukan diatur di sini.
    |
    */
    'prayer' => [
        'enabled' => env('PRAYER_ATTENDANCE_ENABLED', false),
        'start' => env('PRAYER_ATTENDANCE_START', '11:00'),
        'end' => env('PRAYER_ATTENDANCE_END', '13:00'),
        'all_religions' => env('PRAYER_ATTENDANCE_ALL_RELIGIONS', false),
    ],

    /**
     * Hari (ISO: 1=Senin … 7=Minggu) yang aktif secara default.
     */
    'default_active_days' => [1, 2, 3, 4, 5],

    /**
     * Hari yang tetap dibuatkan barisnya tapi non-aktif, supaya admin tinggal
     * mencentang tanpa harus membuat jadwal dari nol.
     */
    'default_inactive_days' => [6],

    'qr_token_secret' => env('QR_TOKEN_SECRET', ''),
    'qr_token_length' => 64,
    'qr_cache_days' => 30,
    'qr_storage_path' => 'qr-codes',

    'scanner_rate_limit' => env('SCANNER_RATE_LIMIT', 60),
];
