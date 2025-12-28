<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Llm\LlmException;
use App\Http\Controllers\Controller;
use App\Services\Llm\GatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GatewayController extends Controller
{
    public function __construct(
        protected GatewayService $gatewayService
    ) {}

    /**
     * OpenAI-compatible chat completions endpoint.
     */
    public function chatCompletions(Request $request): JsonResponse|StreamedResponse
    {
        // Custom validation for OpenAI-compatible message format
        $payload = $request->validate([
            'messages' => 'required|array|min:1',
            'messages.*.role' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $allowedRoles = ['system', 'user', 'assistant', 'tool', 'function'];
                    if (!in_array($value, $allowedRoles)) {
                        $fail("The {$attribute} must be one of: " . implode(', ', $allowedRoles));
                    }
                },
            ],
            'messages.*.content' => [
                'nullable',
                function ($attribute, $value, $fail) use ($request) {
                    // Get the message index from attribute (e.g., "messages.5.content" -> 5)
                    preg_match('/messages\.(\d+)\.content/', $attribute, $matches);
                    $index = $matches[1] ?? null;
                    
                    if ($index !== null) {
                        $messages = $request->input('messages', []);
                        $message = $messages[$index] ?? null;
                        $role = $message['role'] ?? null;
                        
                        // Content is required for system and user roles
                        if (in_array($role, ['system', 'user']) && (empty($value) && $value !== '0')) {
                            $fail("The {$attribute} field is required when role is {$role}.");
                        }
                        
                        // Content is optional for assistant if tool_calls exists
                        // Content is optional for tool role (tool_call_id is required instead)
                        // Content can be null for assistant with tool_calls
                    }
                },
            ],
            'messages.*.tool_calls' => 'sometimes|array',
            'messages.*.tool_call_id' => [
                'sometimes',
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($request) {
                    preg_match('/messages\.(\d+)\.tool_call_id/', $attribute, $matches);
                    $index = $matches[1] ?? null;
                    
                    if ($index !== null) {
                        $messages = $request->input('messages', []);
                        $message = $messages[$index] ?? null;
                        $role = $message['role'] ?? null;
                        
                        // tool_call_id is required for tool role
                        if ($role === 'tool' && empty($value)) {
                            $fail("The {$attribute} field is required when role is tool.");
                        }
                    }
                },
            ],
            'messages.*.name' => 'sometimes|string', // For function role
            'messages.*.function' => 'sometimes|array', // For function role (legacy)
            'model' => 'sometimes|string',
            'max_tokens' => 'sometimes|integer|min:1|max:4096',
            'temperature' => 'sometimes|numeric|min:0|max:2',
            'stream' => 'sometimes|boolean',
        ]);

        // Get request context from middleware
        $user = $request->attributes->get('user');
        $project = $request->attributes->get('project');
        $apiKey = $request->attributes->get('api_key');
        $planConfig = $request->attributes->get('plan_config');
        $requestId = $request->attributes->get('request_id');

        // Get tier preference from header or model parameter
        $requestedTier = $request->header('x-quality');
        
        // If no tier from header, try to map from model parameter
        if (!$requestedTier && isset($payload['model'])) {
            $requestedTier = $this->mapModelToTier($payload['model']);
        }
        
        // Check for decompose trigger
        $forceDecompose = $request->header('x-decompose') === '1';

        // Streaming temporarily disabled - use non-streaming for stability
        $isStreaming = false; // $payload['stream'] ?? false;

        try {
            if ($isStreaming) {
                return $this->streamResponse(
                    payload: $payload,
                    user: $user,
                    project: $project,
                    apiKey: $apiKey,
                    planConfig: $planConfig,
                    requestId: $requestId,
                    requestedTier: $requestedTier
                );
            }

            $response = $this->gatewayService->chatCompletion(
                payload: $payload,
                user: $user,
                project: $project,
                apiKey: $apiKey,
                planConfig: $planConfig,
                requestId: $requestId,
                requestedTier: $requestedTier,
                forceDecompose: $forceDecompose
            );

            return response()->json($response);

        } catch (LlmException $e) {
            return response()->json($e->toArray(), $e->getHttpStatus())
                ->withHeaders($e->getRetryAfter() ? ['Retry-After' => $e->getRetryAfter()] : []);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'message' => 'Internal server error',
                    'type' => 'server_error',
                    'code' => 'internal_error',
                ],
            ], 500);
        }
    }

    /**
     * Handle streaming response.
     */
    protected function streamResponse(
        array $payload,
        $user,
        $project,
        $apiKey,
        array $planConfig,
        string $requestId,
        ?string $requestedTier
    ): StreamedResponse {
        return response()->stream(function () use ($payload, $user, $project, $apiKey, $planConfig, $requestId, $requestedTier) {
            // Disable output buffering for streaming
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            
            try {
                foreach ($this->gatewayService->chatCompletionStream(
                    payload: $payload,
                    user: $user,
                    project: $project,
                    apiKey: $apiKey,
                    planConfig: $planConfig,
                    requestId: $requestId,
                    requestedTier: $requestedTier
                ) as $chunk) {
                    // Final safety check: Don't send error chunks directly to client
                    if (isset($chunk['error']) || isset($chunk['details'])) {
                        $errorType = is_string($chunk['error'] ?? null) ? $chunk['error'] : null;
                        
                        if ($errorType && str_starts_with($errorType, 'ERROR_')) {
                            $errorMessage = $chunk['details']['title'] ?? $chunk['details']['detail'] ?? 'Provider error';
                            throw new \App\Exceptions\Llm\ProviderException($errorMessage, 502);
                        }
                    }
                    
                    echo "data: " . json_encode($chunk) . "\n\n";
                    flush();
                }

                echo "data: [DONE]\n\n";
                flush();

            } catch (LlmException $e) {
                echo "data: " . json_encode([
                    'error' => [
                        'message' => $e->getMessage(),
                        'type' => $e->getErrorType(),
                        'code' => $e->getCode(),
                    ],
                    'isExpected' => true,
                ]) . "\n\n";
                flush();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Unexpected streaming error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                echo "data: " . json_encode([
                    'error' => [
                        'message' => 'Internal server error during streaming',
                        'type' => 'server_error',
                        'code' => 'internal_error',
                    ],
                    'isExpected' => false,
                ]) . "\n\n";
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'X-Request-Id' => $requestId,
        ]);
    }

    /**
     * Map model name to tier.
     */
    protected function mapModelToTier(?string $model): ?string
    {
        if (!$model) {
            return 'fast'; // Default to fast
        }

        $model = strtolower(trim($model));

        // TEMPORARY: Force all requests to fast tier until rate limit issues resolved
        // TODO: Re-enable tier mapping when Anthropic rate limits are sorted out
        if (str_contains($model, 'cf-fast') || str_contains($model, 'haiku')) {
            return 'fast';
        }
        
        if (str_contains($model, 'cf-deep') || str_contains($model, 'opus') || str_contains($model, 'sonnet')) {
            // Temporarily route to fast instead of deep
            return 'fast';
        }

        if (str_contains($model, 'cf-planner') || str_contains($model, 'gpt')) {
            return 'planner';
        }

        if (str_contains($model, 'cf-grace') || str_contains($model, 'llama')) {
            return 'grace';
        }

        // Default to fast for everything else
        return 'fast';
    }

    /**
     * List available models (OpenAI-compatible).
     */
    public function listModels(): JsonResponse
    {
        $aliases = config('litellm.aliases', []);

        $models = [
            [
                'id' => 'cf-fast',
                'object' => 'model',
                'created' => 1700000000,
                'owned_by' => 'codexflow',
                'permission' => [],
                'root' => 'claude-3-5-haiku',
                'parent' => null,
                'description' => 'Fast tier - Claude Haiku 3.5 for quick tasks',
            ],
            [
                'id' => 'cf-deep',
                'object' => 'model',
                'created' => 1700000000,
                'owned_by' => 'codexflow',
                'permission' => [],
                'root' => 'claude-sonnet-4',
                'parent' => null,
                'description' => 'Deep tier - Claude Sonnet 4 for complex logic',
            ],
            [
                'id' => 'cf-grace',
                'object' => 'model',
                'created' => 1700000000,
                'owned_by' => 'codexflow',
                'permission' => [],
                'root' => 'llama-3.1-405b',
                'parent' => null,
                'description' => 'Grace tier - Llama 405B FREE fallback',
            ],
        ];

        return response()->json([
            'object' => 'list',
            'data' => $models,
        ]);
    }
}


