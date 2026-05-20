<?php

use App\Http\Controllers\Api\StudentApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Public (no auth required)
|--------------------------------------------------------------------------
| Endpoints for external HTML card templates (kartu OSIS, kartu perpus, dll).
| Base URL: /api/...
*/

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
