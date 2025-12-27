<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GatewayController;
use App\Http\Controllers\Api\V1\ProjectApiKeyController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\UsageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Auth Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

/*
|--------------------------------------------------------------------------
| Auth Routes (Protected - Sanctum)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/auth')->middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

/*
|--------------------------------------------------------------------------
| Project & API Key Routes (Protected - Sanctum)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Projects
    Route::apiResource('projects', ProjectController::class);

    // Project API Keys
    Route::get('/projects/{project}/keys', [ProjectApiKeyController::class, 'index']);
    Route::post('/projects/{project}/keys', [ProjectApiKeyController::class, 'store']);
    Route::delete('/projects/{project}/keys/{key}', [ProjectApiKeyController::class, 'destroy']);
    Route::post('/projects/{project}/keys/{key}/rotate', [ProjectApiKeyController::class, 'rotate']);

    // Usage
    Route::get('/usage/daily', [UsageController::class, 'daily']);
    Route::get('/usage/summary', [UsageController::class, 'summary']);
});

/*
|--------------------------------------------------------------------------
| LLM Gateway Routes (Protected - API Key)
|--------------------------------------------------------------------------
| OpenAI-compatible endpoint for Cursor AI integration
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

/*
|--------------------------------------------------------------------------
| Admin Routes (Protected - Sanctum + Admin Role)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Health & deployments
    Route::get('/health/deployments', function () {
        // TODO: Implement in PART 4
        return response()->json(['status' => 'ok']);
    });

    // User management
    Route::get('/users', function () {
        // TODO: Implement in PART 5
        return response()->json(['status' => 'ok']);
    });
});

