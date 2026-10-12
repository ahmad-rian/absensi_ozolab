<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Card render queue
    |--------------------------------------------------------------------------
    | Queue that heavy Browsershot card/sheet renders are dispatched onto.
    | Kept separate from the WhatsApp/notification "default" queue so long
    | renders don't delay notifications. Run a worker with:
    |   php artisan queue:work --queue=default,cards
    */
    'queue' => env('CARDS_QUEUE', 'cards'),

    /*
    |--------------------------------------------------------------------------
    | Generate massal
    |--------------------------------------------------------------------------
    | Generate kartu satu sekolah/kelas, pas foto batch, dan generate ulang
    | semua kartu haji. Mati bawaan; nyalakan dengan CARDS_GENERATE_MASSAL=true.
    | Generate per siswa / per peserta tidak terpengaruh.
    */
    'generate_massal' => (bool) env('CARDS_GENERATE_MASSAL', false),
];
