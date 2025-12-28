<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

// Direct test endpoint - no middleware, no auth
Route::post('/test/chat', function () {
    $payload = [
        'model' => 'cf-fast',
        'messages' => [
            ['role' => 'user', 'content' => 'Say "Hello from CodexFlow!"']
        ],
        'max_tokens' => 50,
    ];

    try {
        $baseUrl = config('litellm.base_url', 'http://litellm:4000');
        $masterKey = config('litellm.master_key', 'sk-codexflow-secret-key');

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => "Bearer {$masterKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$baseUrl}/v1/chat/completions", $payload);

        return response()->json([
            'status' => $response->status(),
            'success' => $response->successful(),
            'body' => $response->json(),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

Route::get('/test/health', function () {
    return response()->json([
        'status' => 'ok',
        'litellm_url' => config('litellm.base_url'),
        'time' => now()->toISOString(),
    ]);
});

