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
        $this->baseUrl = rtrim(config('litellm.base_url'), '/');
        $this->masterKey = config('litellm.master_key');
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

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->masterKey}",
                    'Content-Type' => 'application/json',
                    'X-Request-Id' => $requestId,
                ])
                ->post("{$this->baseUrl}/v1/chat/completions", $payload);

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

        $startTime = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->masterKey}",
                    'Content-Type' => 'application/json',
                    'X-Request-Id' => $requestId,
                ])
                ->withOptions(['stream' => true])
                ->post("{$this->baseUrl}/v1/chat/completions", $payload);

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
            'body' => $body,
            'error_type' => $errorType,
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
            return $body['error']['message'];
        }

        // Format 2: LiteLLM format with details
        if (isset($body['error']) && isset($body['details'])) {
            $detail = $body['details'];
            
            if (is_array($detail)) {
                $title = $detail['title'] ?? '';
                $detailText = $detail['detail'] ?? '';
                
                if ($detailText) {
                    // Try to extract API error message from detail
                    if (preg_match('/API Error:\s*```\s*\{.*?"message"\s*:\s*"([^"]+)"\s*.*?\}\s*```/s', $detailText, $matches)) {
                        return $matches[1];
                    }
                    
                    return $title ? "{$title}: {$detailText}" : $detailText;
                }
                
                return $title ?: $body['error'];
            }
            
            return is_string($detail) ? $detail : (string) $body['error'];
        }

        // Format 3: Direct error string
        if (isset($body['error']) && is_string($body['error'])) {
            return $body['error'];
        }

        // Format 4: Message field
        if (isset($body['message'])) {
            return $body['message'];
        }

        return 'Unknown error from LLM provider';
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


