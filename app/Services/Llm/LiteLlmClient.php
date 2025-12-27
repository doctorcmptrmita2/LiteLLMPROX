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

            foreach ($this->parseStreamedResponse($response->body()) as $chunk) {
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
            
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                
                if ($data === '[DONE]') {
                    return;
                }

                $json = json_decode($data, true);
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
        $message = $body['error']['message'] ?? 'Unknown error';

        Log::warning('LiteLLM error response', [
            'status' => $status,
            'tier' => $tier,
            'body' => $body,
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

