<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'service' => 'leadflow-backend',
        'message' => 'LeadFlow Industrial Backend API',
        'health' => '/api/health',
    ]);
});
