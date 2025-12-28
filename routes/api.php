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

// API v1 Root - https://codexflow.dev/api/v1
Route::get('/v1', function () {
    // LiteLLM diagnostic info
    $litellmInfo = [];
    try {
        $baseUrl = config('litellm.base_url');
        $masterKey = config('litellm.master_key');
        $aliases = config('litellm.aliases', []);
        
        $litellmInfo = [
            'base_url' => $baseUrl,
            'master_key_set' => !empty($masterKey),
            'aliases_config' => $aliases,
        ];
        
        // Try to connect to LiteLLM proxy
        if (!empty($masterKey) && !empty($baseUrl)) {
            try {
                $testResponse = \Illuminate\Support\Facades\Http::timeout(5)
                    ->withHeaders(['Authorization' => "Bearer {$masterKey}"])
                    ->get("{$baseUrl}/v1/models");
                
                if ($testResponse->successful()) {
                    $models = $testResponse->json();
                    $modelIds = array_column($models['data'] ?? [], 'id');
                    $expectedAliases = ['cf-fast', 'cf-deep', 'cf-planner', 'cf-grace', 'cf-grace-fallback'];
                    $foundAliases = array_intersect($expectedAliases, $modelIds);
                    $missingAliases = array_diff($expectedAliases, $modelIds);
                    
                    $litellmInfo['proxy'] = [
                        'reachable' => true,
                        'total_models' => count($modelIds),
                        'available_aliases' => array_values($foundAliases),
                        'missing_aliases' => array_values($missingAliases),
                    ];
                } else {
                    $litellmInfo['proxy'] = [
                        'reachable' => false,
                        'error' => "HTTP {$testResponse->status()}",
                    ];
                }
            } catch (\Exception $e) {
                $litellmInfo['proxy'] = [
                    'reachable' => false,
                    'error' => $e->getMessage(),
                ];
            }
        } else {
            $litellmInfo['proxy'] = [
                'reachable' => false,
                'error' => 'Master key or base URL not configured',
            ];
        }
    } catch (\Exception $e) {
        $litellmInfo['error'] = $e->getMessage();
    }
    
    return response()->json([
        'name' => 'CodexFlow.dev API',
        'version' => '1.0',
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'litellm' => $litellmInfo,
        'endpoints' => [
            'auth' => [
                'login' => 'POST /api/v1/auth/login',
                'register' => 'POST /api/v1/auth/register',
                'logout' => 'POST /api/v1/auth/logout',
                'me' => 'GET /api/v1/auth/me',
            ],
            'gateway' => [
                'chat_completions' => 'POST /api/v1/chat/completions',
                'models' => 'GET /api/v1/models',
            ],
            'projects' => [
                'list' => 'GET /api/v1/projects',
                'create' => 'POST /api/v1/projects',
                'show' => 'GET /api/v1/projects/{id}',
                'update' => 'PUT /api/v1/projects/{id}',
                'delete' => 'DELETE /api/v1/projects/{id}',
            ],
            'usage' => [
                'daily' => 'GET /api/v1/usage/daily',
                'summary' => 'GET /api/v1/usage/summary',
            ],
            'test' => [
                'litellm' => 'GET /api/test/litellm',
                'health' => 'GET /api/health',
            ],
        ],
    ]);
});

// LiteLLM Proxy Test (Debug) - Production: https://codexflow.dev/api/test/litellm
Route::get('/test/litellm', function () {
    try {
        $client = app(\App\Services\Llm\LiteLlmClient::class);
        $models = $client->listModels();
        
        $aliases = config('litellm.aliases', []);
        $baseUrl = config('litellm.base_url');
        $masterKey = config('litellm.master_key');
        
        $expectedAliases = ['cf-fast', 'cf-deep', 'cf-planner', 'cf-grace', 'cf-grace-fallback'];
        $availableModels = $models['data'] ?? [];
        $modelIds = array_column($availableModels, 'id');
        
        $foundAliases = array_intersect($expectedAliases, $modelIds);
        $missingAliases = array_diff($expectedAliases, $modelIds);
        
        // Test connection to LiteLLM proxy
        $proxyReachable = false;
        $proxyError = null;
        try {
            $testResponse = \Illuminate\Support\Facades\Http::timeout(5)
                ->withHeaders(['Authorization' => "Bearer {$masterKey}"])
                ->get("{$baseUrl}/v1/models");
            $proxyReachable = $testResponse->successful();
            if (!$proxyReachable) {
                $proxyError = "HTTP {$testResponse->status()}: " . $testResponse->body();
            }
        } catch (\Exception $e) {
            $proxyError = $e->getMessage();
        }
        
        return response()->json([
            'status' => 'ok',
            'environment' => config('app.env'),
            'litellm' => [
                'base_url' => $baseUrl,
                'master_key_set' => !empty($masterKey),
                'master_key_length' => $masterKey ? strlen($masterKey) : 0,
                'aliases_config' => $aliases,
            ],
            'proxy' => [
                'reachable' => $proxyReachable,
                'error' => $proxyError,
                'connected' => !empty($availableModels),
                'total_models' => count($availableModels),
                'available_aliases' => array_values($foundAliases),
                'missing_aliases' => array_values($missingAliases),
                'all_models' => $modelIds,
            ],
            'diagnosis' => [
                'master_key_missing' => empty($masterKey),
                'proxy_not_reachable' => !$proxyReachable,
                'proxy_not_responding' => empty($availableModels),
                'aliases_not_found' => !empty($missingAliases),
                'recommendations' => [],
            ],
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'error' => $e->getMessage(),
            'trace' => config('app.debug') ? $e->getTraceAsString() : null,
        ], 500);
    }
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


