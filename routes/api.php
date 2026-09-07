<?php

use App\Http\Controllers\Api\StudentApiController;
use App\Http\Controllers\Api\StudioController;
use App\Http\Controllers\Api\StudioPhotoController;
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

// `feature:master_siswa` wajib ikut di sini: tanpa itu toggle Master Data bisa
// dilewati sepenuhnya lewat GET /api/students.
Route::middleware(['web', 'auth', 'permission:siswa.access', 'feature:master_siswa', 'throttle:60,1'])->group(function () {
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

/*
|--------------------------------------------------------------------------
| Tyas Studio — bertoken, tanpa sesi
|--------------------------------------------------------------------------
|
| Studio adalah aplikasi terpisah di tyasstudio.ozolab.id. Ia tidak punya
| sesi di sini, jadi grup ini TIDAK memakai middleware `web`.
|
| Akibatnya global scope `school` mati total: ia membaca school_id milik user
| yang login, dan di sini tidak ada yang login. Setiap query di balik grup ini
| wajib dibatasi sendiri lewat `StudioRequest` — lihat peringatan di
| AuthenticateStudioToken.
|
| Batas laju dilonggarkan untuk unggah karena satu sesi foto mengirim berkas
| besar lalu menanyakan statusnya berulang kali.
*/
Route::middleware(['studio-token', 'throttle:120,1'])->prefix('studio')->name('studio.')->group(function () {
    Route::get('schools', [StudioController::class, 'schools']);
    Route::get('schools/{school}/classrooms', [StudioController::class, 'classrooms']);
    Route::get('schools/{school}/students', [StudioController::class, 'schoolStudents']);
    Route::get('classrooms/{classroom}/students', [StudioController::class, 'classroomStudents']);

    Route::get('students', [StudioController::class, 'students']);
    Route::get('students/by-qr/{token}', [StudioController::class, 'byQr']);
    Route::get('students/{student}', [StudioController::class, 'student']);

    Route::post('students/{student}/photo', [StudioPhotoController::class, 'store']);
    // Jepretan asli dari folder Drive siswa, untuk memotong ULANG tanpa
    // memfoto ulang anaknya. Salinan di server sudah dihapus begitu naik ke
    // Drive, jadi ini satu-satunya jalan mengambilnya kembali.
    Route::get('students/{student}/ori', [StudioPhotoController::class, 'original']);
    Route::get('uploads/{upload}', [StudioPhotoController::class, 'status']);
});
