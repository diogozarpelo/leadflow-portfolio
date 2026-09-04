<?php

use App\Http\Controllers\Api\LeadController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'service' => 'leadflow-backend',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::post('/leads', [LeadController::class, 'store'])
    ->middleware('throttle:lead-submissions');
