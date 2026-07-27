<?php

use App\Http\Controllers\Api\StudentApiController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

// Telegram bot webhook (per school). Secret validated inside the controller.
Route::post('telegram/webhook/{school}', [TelegramWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('telegram.webhook');

/*
|--------------------------------------------------------------------------
| API Routes — butuh sesi login
|--------------------------------------------------------------------------
|
| Sebelumnya grup ini terbuka tanpa auth dan `GET /api/students` membocorkan
| seluruh siswa dari semua sekolah. Sekarang memakai sesi web yang sama dengan
| panel admin; hasilnya otomatis ter-scope ke sekolah user oleh global scope.
|
*/

Route::middleware(['web', 'auth', 'throttle:60,1'])->group(function () {
    // Schools
    Route::get('schools', [StudentApiController::class, 'schools']);
    Route::get('schools/{school}/students', [StudentApiController::class, 'schoolStudents']);

    // Students
    Route::get('students', [StudentApiController::class, 'index']);
    Route::get('students/{student}', [StudentApiController::class, 'show']);
    Route::get('students/{student}/qr', [StudentApiController::class, 'qr']);

    // Lookup
    Route::get('students/by-nis/{nis}', [StudentApiController::class, 'byNis']);
    Route::get('students/by-qr/{token}', [StudentApiController::class, 'byQr']);
});
