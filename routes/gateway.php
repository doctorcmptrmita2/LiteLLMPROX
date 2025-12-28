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

$gatewayMiddleware = [
    \App\Http\Middleware\RequestIdMiddleware::class,
    \App\Http\Middleware\AuthenticateProjectApiKey::class,
    \App\Http\Middleware\CheckUserStatus::class,
    \App\Http\Middleware\RateLimitMiddleware::class,
    \App\Http\Middleware\QuotaCheckMiddleware::class,
];

// Standard OpenAI-compatible endpoint: /v1/chat/completions
Route::prefix('v1')->middleware($gatewayMiddleware)->group(function () {
    Route::post('/chat/completions', [GatewayController::class, 'chatCompletions']);
    Route::get('/models', [GatewayController::class, 'listModels']);
});

/*
|--------------------------------------------------------------------------
| Cursor IDE Special Endpoint
|--------------------------------------------------------------------------
| LiteLLM recommends using /cursor prefix for Cursor IDE integration
| Base URL in Cursor: https://api.codexflow.dev/v1/cursor
|--------------------------------------------------------------------------
*/
Route::prefix('v1/cursor')->middleware($gatewayMiddleware)->group(function () {
    Route::post('/chat/completions', [GatewayController::class, 'chatCompletions']);
    Route::get('/models', [GatewayController::class, 'listModels']);
});

// Health check (no auth required)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

