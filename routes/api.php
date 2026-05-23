<?php

use App\Http\Controllers\Api\StudentApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Public (rate-limited, no auth)
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:60,1')->group(function () {
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
