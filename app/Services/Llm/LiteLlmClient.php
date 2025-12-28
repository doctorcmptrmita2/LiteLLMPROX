<?php

namespace App\Services\Llm;

use App\Exceptions\Llm\BadRequestException;
use App\Exceptions\Llm\ProviderException;
use App\Exceptions\Llm\RateLimitException;
use App\Exceptions\Llm\TimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiteLlmClient
{
    protected string $baseUrl;
    protected string $masterKey;

    public function __construct()
    {
        $baseUrl = config('litellm.base_url');
        
        if (empty($baseUrl)) {
            throw new \InvalidArgumentException('LITELLM_BASE_URL is not configured');
        }
        
        // Normalize base URL - remove trailing slashes
        $baseUrl = rtrim($baseUrl, '/');
        
        // Parse URL to ensure it's valid
        $parsed = parse_url($baseUrl);
        if ($parsed === false || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            throw new \InvalidArgumentException("Invalid LITELLM_BASE_URL format: {$baseUrl}. Expected format: http://host:port");
        }
        
        // Rebuild URL without path (LiteLLM proxy should be at root)
        $this->baseUrl = "{$parsed['scheme']}://{$parsed['host']}" . (isset($parsed['port']) ? ":{$parsed['port']}" : '');
        
        // If original URL had a path, log warning
        if (isset($parsed['path']) && $parsed['path'] !== '/') {
            Log::warning('LiteLLM base URL contains path segment - it will be ignored', [
                'original_url' => $baseUrl,
                'normalized_url' => $this->baseUrl,
                'path_removed' => $parsed['path'],
                'note' => 'LiteLLM proxy should be accessible at root (e.g., http://localhost:4000)',
            ]);
        }
        
        $this->masterKey = config('litellm.master_key');
        
        if (empty($this->masterKey)) {
            Log::warning('LITELLM_MASTER_KEY is not configured');
        }
    }

    /**
     * Send chat completion request to LiteLLM proxy.
     */
    public function chatCompletion(array $payload, string $tier, string $requestId): array
    {
        $alias = config("litellm.aliases.{$tier}");
        $timeout = config("litellm.tiers.{$tier}.timeout", 60);

        // Set the model to the LiteLLM alias
        $payload['model'] = $alias;

        $startTime = microtime(true);

        $endpoint = "{$this->baseUrl}/v1/chat/completions";
        
        Log::debug('LiteLLM request', [
            'request_id' => $requestId,
            'tier' => $tier,
            'endpoint' => $endpoint,
            'base_url' => $this->baseUrl,
        ]);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->masterKey}",
                    'Content-Type' => 'application/json',
                    'X-Request-Id' => $requestId,
                ])
                ->post($endpoint, $payload);

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            return $this->handleResponse($response, $latencyMs, $tier);

        } catch (ConnectionException $e) {
            Log::error('LiteLLM connection failed', [
                'request_id' => $requestId,
                'tier' => $tier,
                'error' => $e->getMessage(),
            ]);

            throw new TimeoutException('Connection to LLM provider timed out');
        }
    }

    /**
     * Send streaming chat completion request.
     */
    public function chatCompletionStream(array $payload, string $tier, string $requestId): \Generator
    {
        $alias = config("litellm.aliases.{$tier}");
        $timeout = config("litellm.tiers.{$tier}.timeout", 60);

        $payload['model'] = $alias;
        $payload['stream'] = true;

        $endpoint = "{$this->baseUrl}/v1/chat/completions";
        
        Log::debug('LiteLLM streaming request', [
            'request_id' => $requestId,
            'tier' => $tier,
            'endpoint' => $endpoint,
            'base_url' => $this->baseUrl,
        ]);

        $startTime = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->masterKey}",
                    'Content-Type' => 'application/json',
                    'X-Request-Id' => $requestId,
                ])
                ->withOptions(['stream' => true])
                ->post($endpoint, $payload);

            // Check if response is successful
            if (!$response->successful()) {
                // Try to parse error from body even if response failed
                $body = $response->body();
                
                // If body is not empty, try to parse as JSON
                if (!empty($body)) {
                    $errorBody = json_decode($body, true);
                    if ($errorBody && (isset($errorBody['error']) || isset($errorBody['details']))) {
                        $errorMessage = $this->extractErrorMessage($errorBody);
                        
                        Log::error('LiteLLM streaming HTTP error with body', [
                            'request_id' => $requestId,
                            'tier' => $tier,
                            'status' => $response->status(),
                            'body' => $errorBody,
                            'error_message' => $errorMessage,
                        ]);
                        
                        throw new ProviderException($errorMessage, $response->status());
                    }
                }
                
                // Fallback to standard error handling
                $this->handleError($response, $tier);
            }

            $firstChunk = true;
            $timeToFirstToken = null;
            $hasError = false;
            $bodyContent = $response->body();

            // Check if body contains error before parsing stream
            if (!empty($bodyContent)) {
                // Try to detect if entire body is an error (not SSE stream)
                $firstLine = explode("\n", $bodyContent)[0] ?? '';
                if (str_starts_with(trim($firstLine), '{') && !str_contains($bodyContent, 'data: ')) {
                    // Looks like a JSON error response, not SSE
                    $errorBody = json_decode($bodyContent, true);
                    if ($errorBody && (isset($errorBody['error']) || isset($errorBody['details']))) {
                        $errorMessage = $this->extractErrorMessage($errorBody);
                        
                        Log::error('LiteLLM streaming error in response body', [
                            'request_id' => $requestId,
                            'tier' => $tier,
                            'body' => $errorBody,
                            'error_message' => $errorMessage,
                        ]);
                        
                        throw new ProviderException($errorMessage, 502);
                    }
                }
            }

            foreach ($this->parseStreamedResponse($bodyContent) as $chunk) {
                // Check for error in chunk (LiteLLM can send errors in stream)
                // Format 1: Direct error field (string or object)
                if (isset($chunk['error'])) {
                    $hasError = true;
                    $errorMessage = $this->extractErrorMessage($chunk);
                    
                    Log::error('LiteLLM streaming error in chunk', [
                        'request_id' => $requestId,
                        'tier' => $tier,
                        'chunk' => $chunk,
                        'error_message' => $errorMessage,
                    ]);
                    
                    throw new ProviderException($errorMessage, 502);
                }
                
                // Format 2: LiteLLM error format with details
                if (isset($chunk['details'])) {
                    $hasError = true;
                    $errorMessage = $this->extractErrorMessage($chunk);
                    
                    Log::error('LiteLLM streaming error with details in chunk', [
                        'request_id' => $requestId,
                        'tier' => $tier,
                        'chunk' => $chunk,
                        'error_message' => $errorMessage,
                    ]);
                    
                    throw new ProviderException($errorMessage, 502);
                }
                
                // Format 3: Error in choices delta (alternative error format)
                if (isset($chunk['choices'][0]['delta']['error'])) {
                    $hasError = true;
                    $errorData = $chunk['choices'][0]['delta']['error'];
                    $errorMessage = $this->extractErrorMessage($errorData);
                    
                    Log::error('LiteLLM streaming error in choice delta', [
                        'request_id' => $requestId,
                        'tier' => $tier,
                        'error_data' => $errorData,
                        'error_message' => $errorMessage,
                    ]);
                    
                    throw new ProviderException($errorMessage, 502);
                }
                
                // Format 4: Check if chunk itself is an error (no choices, no content, just error info)
                if (!isset($chunk['choices']) && !isset($chunk['content']) && (isset($chunk['error']) || isset($chunk['details']))) {
                    $hasError = true;
                    $errorMessage = $this->extractErrorMessage($chunk);
                    
                    Log::error('LiteLLM streaming error-only chunk', [
                        'request_id' => $requestId,
                        'tier' => $tier,
                        'chunk' => $chunk,
                        'error_message' => $errorMessage,
                    ]);
                    
                    throw new ProviderException($errorMessage, 502);
                }

                if ($firstChunk) {
                    $timeToFirstToken = (int) ((microtime(true) - $startTime) * 1000);
                    $firstChunk = false;
                }

                yield [
                    'chunk' => $chunk,
                    'time_to_first_token_ms' => $timeToFirstToken,
                ];
            }

        } catch (ConnectionException $e) {
            Log::error('LiteLLM streaming connection failed', [
                'request_id' => $requestId,
                'tier' => $tier,
                'error' => $e->getMessage(),
            ]);

            throw new TimeoutException('Connection to LLM provider timed out');
        } catch (\Exception $e) {
            // Re-throw LLM exceptions as-is
            if ($e instanceof \App\Exceptions\Llm\LlmException) {
                throw $e;
            }

            // Wrap other exceptions
            Log::error('LiteLLM streaming unexpected error', [
                'request_id' => $requestId,
                'tier' => $tier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ProviderException(
                'Streaming error: ' . $e->getMessage(),
                502
            );
        }
    }

    /**
     * Parse SSE streamed response.
     */
    protected function parseStreamedResponse(string $body): \Generator
    {
        $lines = explode("\n", $body);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            $data = null;
            
            // Standard SSE format: "data: {...}"
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                
                if ($data === '[DONE]') {
                    return;
                }
            } 
            // Non-standard: Direct JSON (some providers send errors this way)
            elseif (str_starts_with($line, '{') || str_starts_with($line, '[')) {
                $data = $line;
            }
            
            if ($data === null) {
                continue;
            }

            $json = json_decode($data, true);
            
            // Handle JSON decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to parse SSE chunk', [
                    'data' => $data,
                    'json_error' => json_last_error_msg(),
                ]);
                continue;
            }
            
            if ($json) {
                yield $json;
            }
        }
    }

    /**
     * Handle LiteLLM response.
     */
    protected function handleResponse(Response $response, int $latencyMs, string $tier): array
    {
        if (!$response->successful()) {
            $this->handleError($response, $tier);
        }

        $data = $response->json();
        $data['_meta'] = [
            'latency_ms' => $latencyMs,
            'tier' => $tier,
        ];

        return $data;
    }

    /**
     * Handle error responses from LiteLLM.
     */
    protected function handleError(Response $response, string $tier): void
    {
        $status = $response->status();
        $body = $response->json();
        
        // LiteLLM can return errors in different formats
        $message = $this->extractErrorMessage($body);
        $errorType = $body['error'] ?? null;
        $details = $body['details'] ?? null;

        Log::warning('LiteLLM error response', [
            'status' => $status,
            'tier' => $tier,
            'base_url' => $this->baseUrl,
            'body' => $body,
            'error_type' => $errorType,
            'extracted_message' => $message,
        ]);

        match ($status) {
            400 => throw new BadRequestException($message),
            429 => throw new RateLimitException(
                $message,
                (int) ($response->header('Retry-After') ?? 60)
            ),
            504, 408 => throw new TimeoutException($message),
            default => throw new ProviderException($message, $status),
        };
    }

    /**
     * Extract error message from LiteLLM response (handles multiple formats).
     */
    protected function extractErrorMessage(array $body): string
    {
        // Format 1: Standard OpenAI format
        if (isset($body['error']['message'])) {
            $message = $body['error']['message'];
            return $this->enhanceErrorMessage($message);
        }

        // Format 2: Nested error structure (error.error.message)
        if (isset($body['error']['error']['message'])) {
            $message = $body['error']['error']['message'];
            return $this->enhanceErrorMessage($message);
        }

        // Format 3: LiteLLM format with details (ERROR_OPENAI, ERROR_ANTHROPIC, etc.)
        if (isset($body['error']) && isset($body['details'])) {
            $errorType = is_string($body['error']) ? $body['error'] : null;
            $detail = $body['details'];
            
            if (is_array($detail)) {
                $title = $detail['title'] ?? '';
                $detailText = $detail['detail'] ?? '';
                
                if ($detailText) {
                    // Try to extract nested JSON error from detail text
                    // Pattern: API Error: ``` {...} ``` or just ``` {...} ```
                    $jsonPatterns = [
                        '/API Error:\s*```\s*(\{.*?\})\s*```/s',
                        '/```\s*(\{.*?\})\s*```/s',
                    ];
                    
                    foreach ($jsonPatterns as $pattern) {
                        if (preg_match($pattern, $detailText, $jsonMatches)) {
                            $errorJson = json_decode($jsonMatches[1], true);
                            
                            if ($errorJson) {
                                // Try nested error.message
                                if (isset($errorJson['error']['message'])) {
                                    $message = $errorJson['error']['message'];
                                    // Check for route duplication error
                                    if (preg_match('/route.*?\/chat\/completions\/chat\/completions/i', $message)) {
                                        return $this->enhanceErrorMessage(
                                            'Route duplication error: LiteLLM base URL contains path segment. ' .
                                            'LITELLM_BASE_URL should be just the host and port (e.g., http://localhost:4000), ' .
                                            'not include /api/v1/chat/completions path.'
                                        );
                                    }
                                    return $this->enhanceErrorMessage($message);
                                }
                                
                                // Try error.message
                                if (isset($errorJson['message'])) {
                                    $message = $errorJson['message'];
                                    if (preg_match('/route.*?\/chat\/completions\/chat\/completions/i', $message)) {
                                        return $this->enhanceErrorMessage(
                                            'Route duplication error: LiteLLM base URL contains path segment. ' .
                                            'LITELLM_BASE_URL should be just the host and port (e.g., http://localhost:4000), ' .
                                            'not include /api/v1/chat/completions path.'
                                        );
                                    }
                                    return $this->enhanceErrorMessage($message);
                                }
                                
                                // Try provider.body (which might be a JSON string)
                                if (isset($errorJson['provider']['body'])) {
                                    $bodyStr = $errorJson['provider']['body'];
                                    
                                    // Try to parse as JSON string first
                                    $bodyJson = json_decode($bodyStr, true);
                                    if ($bodyJson && isset($bodyJson['message'])) {
                                        $message = $bodyJson['message'];
                                        // Check for route duplication
                                        if (preg_match('/route.*?\/chat\/completions\/chat\/completions/i', $message)) {
                                            return $this->enhanceErrorMessage(
                                                'Route duplication error: LiteLLM base URL contains path segment. ' .
                                                'LITELLM_BASE_URL should be just the host and port (e.g., http://localhost:4000), ' .
                                                'not include /api/v1/chat/completions path.'
                                            );
                                        }
                                        return $this->enhanceErrorMessage($message);
                                    }
                                    
                                    // If not JSON, check if it contains route duplication message
                                    if (preg_match('/route.*?\/chat\/completions\/chat\/completions/i', $bodyStr)) {
                                        return $this->enhanceErrorMessage(
                                            'Route duplication error: LiteLLM base URL contains path segment. ' .
                                            'LITELLM_BASE_URL should be just the host and port (e.g., http://localhost:4000), ' .
                                            'not include /api/v1/chat/completions path.'
                                        );
                                    }
                                }
                                
                                // Try provider.message (alternative format)
                                if (isset($errorJson['provider']['message'])) {
                                    $message = $errorJson['provider']['message'];
                                    if (preg_match('/route.*?\/chat\/completions\/chat\/completions/i', $message)) {
                                        return $this->enhanceErrorMessage(
                                            'Route duplication error: LiteLLM base URL contains path segment. ' .
                                            'LITELLM_BASE_URL should be just the host and port (e.g., http://localhost:4000), ' .
                                            'not include /api/v1/chat/completions path.'
                                        );
                                    }
                                    return $this->enhanceErrorMessage($message);
                                }
                            }
                        }
                    }
                    
                    // Also check detailText directly for route duplication
                    if (preg_match('/route.*?\/chat\/completions\/chat\/completions/i', $detailText)) {
                        return $this->enhanceErrorMessage(
                            'Route duplication error: LiteLLM base URL contains path segment. ' .
                            'LITELLM_BASE_URL should be just the host and port (e.g., http://localhost:4000), ' .
                            'not include /api/v1/chat/completions path.'
                        );
                    }
                    
                    // Try simpler pattern: "message": "..."
                    if (preg_match('/"message"\s*:\s*"([^"]+)"/', $detailText, $matches)) {
                        return $this->enhanceErrorMessage($matches[1]);
                    }
                    
                    // Build message from title and detail
                    if ($title && $detailText) {
                        // If error type is known (ERROR_OPENAI, etc.), include it
                        if ($errorType && str_starts_with($errorType, 'ERROR_')) {
                            $message = "{$title} ({$errorType}): {$detailText}";
                        } else {
                            $message = "{$title}: {$detailText}";
                        }
                    } else {
                        $message = $title ?: $detailText;
                    }
                    
                    return $this->enhanceErrorMessage($message);
                }
                
                // If only title exists
                if ($title) {
                    $message = $errorType && str_starts_with($errorType, 'ERROR_') 
                        ? "{$title} ({$errorType})" 
                        : $title;
                    return $this->enhanceErrorMessage($message);
                }
                
                // Fallback to error type if it's a string
                if ($errorType && str_starts_with($errorType, 'ERROR_')) {
                    return $this->enhanceErrorMessage("Provider error: {$errorType}");
                }
            }
            
            // If details is a string
            if (is_string($detail)) {
                return $this->enhanceErrorMessage($detail);
            }
        }

        // Format 4: Direct error string (ERROR_OPENAI, ERROR_ANTHROPIC, etc.)
        if (isset($body['error']) && is_string($body['error'])) {
            $errorStr = $body['error'];
            
            // If it's an error code like ERROR_OPENAI, provide more context
            if (str_starts_with($errorStr, 'ERROR_')) {
                return $this->enhanceErrorMessage("Provider error: {$errorStr}");
            }
            
            return $this->enhanceErrorMessage($errorStr);
        }

        // Format 5: Message field
        if (isset($body['message'])) {
            return $this->enhanceErrorMessage($body['message']);
        }

        // Format 6: Direct error string in details
        if (isset($body['details']['detail'])) {
            $detailText = $body['details']['detail'];
            
            // Try to extract "Server Error" or similar from detail
            if (preg_match('/Server Error/i', $detailText)) {
                return $this->enhanceErrorMessage('Server Error');
            }
            
            // Try to extract message from detail text (e.g., from JSON in code blocks)
            if (preg_match('/"message"\s*:\s*"([^"]+)"/', $detailText, $matches)) {
                return $this->enhanceErrorMessage($matches[1]);
            }
            
            // Return the detail text itself
            return $this->enhanceErrorMessage($detailText);
        }

        // Format 7: Check if body itself is a simple error message (string)
        if (is_string($body) && strtolower(trim($body)) === 'server error') {
            return $this->enhanceErrorMessage('Server Error');
        }

        return 'Unknown error from LLM provider';
    }

    /**
     * Enhance error message with helpful context for common issues.
     */
    protected function enhanceErrorMessage(string $message): string
    {
        // Check for generic "Server Error"
        if (strtolower(trim($message)) === 'server error') {
            return 'Server Error: LiteLLM proxy encountered an internal error. This may be temporary. Please try again or contact support if the issue persists. (Proxy: ' . $this->baseUrl . ')';
        }
        
        // Check for URL duplication issues (route contains /chat/completions twice)
        if (preg_match('/route.*?\/chat\/completions\/chat\/completions/i', $message)) {
            return 'Route duplication error: The LiteLLM base URL is incorrectly configured. ' .
                   'LITELLM_BASE_URL should be just the host and port (e.g., http://localhost:4000), ' .
                   'NOT include any path like /api/v1/chat/completions. ' .
                   'Current base URL: ' . $this->baseUrl . '. ' .
                   'Please check your .env file and ensure LITELLM_BASE_URL does not contain any path segments.';
        }
        
        // Check for 404 errors
        if (str_contains($message, '404') || str_contains($message, 'not found')) {
            return $message . ' (HINT: Verify LiteLLM proxy is running and accessible at ' . $this->baseUrl . ')';
        }
        
        // Check for 500/502/503 errors
        if (preg_match('/50[0-3]/', $message) || str_contains(strtolower($message), 'internal server error')) {
            return $message . ' (HINT: LiteLLM proxy may be experiencing issues. Please try again in a moment.)';
        }
        
        return $message;
    }

    /**
     * Get available models from LiteLLM.
     */
    public function listModels(): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->masterKey}",
                ])
                ->get("{$this->baseUrl}/v1/models");

            if ($response->successful()) {
                return $response->json();
            }

            return ['data' => []];

        } catch (\Exception $e) {
            Log::warning('Failed to list LiteLLM models', ['error' => $e->getMessage()]);
            return ['data' => []];
        }
    }
}


