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

        // Get tier preference from header
        $requestedTier = $request->header('x-quality');
        
        // Check for decompose trigger
        $forceDecompose = $request->header('x-decompose') === '1';

        // Check if streaming requested
        $isStreaming = $payload['stream'] ?? false;

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
                    echo "data: " . json_encode($chunk) . "\n\n";
                    ob_flush();
                    flush();
                }

                echo "data: [DONE]\n\n";
                ob_flush();
                flush();

            } catch (LlmException $e) {
                // Send error in OpenAI-compatible format
                echo "data: " . json_encode([
                    'error' => [
                        'message' => $e->getMessage(),
                        'type' => $e->getErrorType(),
                        'code' => $e->getCode(),
                    ],
                    'isExpected' => true,
                ]) . "\n\n";
                ob_flush();
                flush();
            } catch (\Exception $e) {
                // Catch any unexpected errors
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
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'X-Request-Id' => $requestId,
        ]);
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


