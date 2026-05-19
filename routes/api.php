<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Endpoints for scanner devices and WhatsApp webhooks.
| These will be enabled when the scanner module is built (Phase 3).
|
*/

// POST /api/scanner/scan — QR scanner endpoint (auth via device API token)
// Route::post('/scanner/scan', [ScannerController::class, 'scan'])
//     ->middleware('throttle:scanner');

// POST /api/webhooks/whatsapp/status — WhatsApp delivery status callback
// Route::post('/webhooks/whatsapp/status', [WhatsAppWebhookController::class, 'status']);
