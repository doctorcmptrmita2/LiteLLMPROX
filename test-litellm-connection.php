<?php

/**
 * Test script to check LiteLLM proxy connection and API calls
 * Run: php test-litellm-connection.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Llm\LiteLlmClient;
use Illuminate\Support\Facades\Log;

echo "=== LiteLLM Proxy Connection Test ===\n\n";

// Test 1: List models
echo "1. Testing model list...\n";
$client = new LiteLlmClient();
$models = $client->listModels();
echo "   ✓ Models found: " . count($models['data'] ?? []) . "\n";
foreach ($models['data'] ?? [] as $model) {
    echo "     - {$model['id']}\n";
}

// Test 2: Test cf-planner (OpenRouter -> OpenAI)
echo "\n2. Testing cf-planner (OpenRouter -> OpenAI)...\n";
try {
    $payload = [
        'model' => 'cf-planner',
        'messages' => [
            ['role' => 'user', 'content' => 'Say hello']
        ],
        'max_tokens' => 10,
        'stream' => false,
    ];
    
    $response = $client->chatCompletion($payload, 'planner', 'test-request-' . time());
    echo "   ✓ Success! Response: " . substr($response['choices'][0]['message']['content'] ?? 'No content', 0, 50) . "...\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "   Class: " . get_class($e) . "\n";
}

// Test 3: Test cf-grace-fallback (OpenRouter -> OpenAI)
echo "\n3. Testing cf-grace-fallback (OpenRouter -> OpenAI)...\n";
try {
    $payload = [
        'model' => 'cf-grace-fallback',
        'messages' => [
            ['role' => 'user', 'content' => 'Say hello']
        ],
        'max_tokens' => 10,
        'stream' => false,
    ];
    
    $response = $client->chatCompletion($payload, 'grace_fallback', 'test-request-' . time());
    echo "   ✓ Success! Response: " . substr($response['choices'][0]['message']['content'] ?? 'No content', 0, 50) . "...\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "   Class: " . get_class($e) . "\n";
}

// Test 4: Test streaming (cf-planner)
echo "\n4. Testing streaming (cf-planner)...\n";
try {
    $payload = [
        'model' => 'cf-planner',
        'messages' => [
            ['role' => 'user', 'content' => 'Say hello']
        ],
        'max_tokens' => 10,
        'stream' => true,
    ];
    
    $chunkCount = 0;
    foreach ($client->chatCompletionStream($payload, 'planner', 'test-stream-' . time()) as $chunkData) {
        $chunk = $chunkData['chunk'];
        if (isset($chunk['choices'][0]['delta']['content'])) {
            echo $chunk['choices'][0]['delta']['content'];
            $chunkCount++;
        }
    }
    echo "\n   ✓ Success! Received {$chunkCount} chunks\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "   Class: " . get_class($e) . "\n";
    if (method_exists($e, 'getTraceAsString')) {
        echo "   Trace:\n" . substr($e->getTraceAsString(), 0, 500) . "\n";
    }
}

echo "\n=== Test Complete ===\n";


