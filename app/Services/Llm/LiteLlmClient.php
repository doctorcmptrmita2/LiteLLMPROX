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

            if (!$response->successful()) {
                $this->handleError($response, $tier);
            }

            $firstChunk = true;
            $timeToFirstToken = null;
            $hasError = false;

            foreach ($this->parseStreamedResponse($response->body()) as $chunk) {
                // Check for error in chunk (LiteLLM can send errors in stream)
                if (isset($chunk['error'])) {
                    $hasError = true;
                    $errorMessage = $this->extractErrorMessage($chunk);
                    
                    Log::error('LiteLLM streaming error in chunk', [
                        'request_id' => $requestId,
                        'tier' => $tier,
                        'chunk' => $chunk,
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
            
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                
                if ($data === '[DONE]') {
                    return;
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

        // Format 3: LiteLLM format with details
        if (isset($body['error']) && isset($body['details'])) {
            $detail = $body['details'];
            
            if (is_array($detail)) {
                $title = $detail['title'] ?? '';
                $detailText = $detail['detail'] ?? '';
                
                if ($detailText) {
                    // Try to extract nested JSON error from detail text
                    // Pattern: API Error: ``` {...} ```
                    if (preg_match('/API Error:\s*```\s*(\{.*?\})\s*```/s', $detailText, $jsonMatches)) {
                        $errorJson = json_decode($jsonMatches[1], true);
                        
                        if ($errorJson) {
                            // Try nested error.message
                            if (isset($errorJson['error']['message'])) {
                                return $this->enhanceErrorMessage($errorJson['error']['message']);
                            }
                            
                            // Try error.message
                            if (isset($errorJson['message'])) {
                                return $this->enhanceErrorMessage($errorJson['message']);
                            }
                            
                            // Try provider.body (which might be a JSON string)
                            if (isset($errorJson['provider']['body'])) {
                                $bodyJson = json_decode($errorJson['provider']['body'], true);
                                if ($bodyJson && isset($bodyJson['message'])) {
                                    return $this->enhanceErrorMessage($bodyJson['message']);
                                }
                            }
                        }
                    }
                    
                    // Try simpler pattern: "message": "..."
                    if (preg_match('/"message"\s*:\s*"([^"]+)"/', $detailText, $matches)) {
                        return $this->enhanceErrorMessage($matches[1]);
                    }
                    
                    $message = $title ? "{$title}: {$detailText}" : $detailText;
                    return $this->enhanceErrorMessage($message);
                }
                
                return $this->enhanceErrorMessage($title ?: $body['error']);
            }
            
            return $this->enhanceErrorMessage(is_string($detail) ? $detail : (string) $body['error']);
        }

        // Format 4: Direct error string
        if (isset($body['error']) && is_string($body['error'])) {
            return $this->enhanceErrorMessage($body['error']);
        }

        // Format 5: Message field
        if (isset($body['message'])) {
            return $this->enhanceErrorMessage($body['message']);
        }

        return 'Unknown error from LLM provider';
    }

    /**
     * Enhance error message with helpful context for common issues.
     */
    protected function enhanceErrorMessage(string $message): string
    {
        // Check for URL duplication issues
        if (preg_match('/route\s+.*?\/chat\/completions\/chat\/completions/i', $message)) {
            return $message . ' (HINT: Check LITELLM_BASE_URL in .env - it should be just the host and port, e.g., http://localhost:4000)';
        }
        
        // Check for 404 errors
        if (str_contains($message, '404') || str_contains($message, 'not found')) {
            return $message . ' (HINT: Verify LiteLLM proxy is running and accessible at ' . $this->baseUrl . ')';
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


