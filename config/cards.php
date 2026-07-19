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
];
