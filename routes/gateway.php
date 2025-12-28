<?php

use App\Http\Controllers\Api\V1\GatewayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LLM Gateway Routes (OpenAI-compatible)
|--------------------------------------------------------------------------
| These routes are accessible at /v1/... (without /api prefix)
| This is required for Cursor AI compatibility
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->middleware([
    \App\Http\Middleware\RequestIdMiddleware::class,
    \App\Http\Middleware\AuthenticateProjectApiKey::class,
    \App\Http\Middleware\CheckUserStatus::class,
    \App\Http\Middleware\RateLimitMiddleware::class,
    \App\Http\Middleware\QuotaCheckMiddleware::class,
])->group(function () {
    // OpenAI-compatible chat completions endpoint
    Route::post('/chat/completions', [GatewayController::class, 'chatCompletions']);
    
    // Model listing (for Cursor compatibility)
    Route::get('/models', [GatewayController::class, 'listModels']);
});

// Health check (no auth required)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

