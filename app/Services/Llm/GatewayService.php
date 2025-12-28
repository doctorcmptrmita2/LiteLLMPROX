<?php

namespace App\Services\Llm;

use App\Exceptions\Llm\LlmException;
use App\Exceptions\Llm\QuotaExceededException;
use App\Models\Project;
use App\Models\ProjectApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class GatewayService
{
    public function __construct(
        protected LiteLlmClient $client,
        protected TierSelector $tierSelector,
        protected QuotaService $quotaService,
        protected CacheService $cacheService,
        protected AdmissionControl $admissionControl,
        protected TelemetryService $telemetryService,
        protected DecomposeService $decomposeService
    ) {}

    /**
     * Process chat completion request.
     */
    public function chatCompletion(
        array $payload,
        User $user,
        Project $project,
        ?ProjectApiKey $apiKey,
        array $planConfig,
        string $requestId,
        ?string $requestedTier = null,
        bool $forceDecompose = false
    ): array {
        $startTime = microtime(true);

        try {
            // Check if decompose should be triggered
            if ($this->shouldDecompose($payload, $forceDecompose)) {
                return $this->decomposeService->process(
                    payload: $payload,
                    user: $user,
                    project: $project,
                    apiKey: $apiKey,
                    planConfig: $planConfig,
                    requestId: $requestId
                );
            }

            // Select tier based on quota
            $tierResult = $this->tierSelector->selectTier($user, $planConfig, $requestedTier);

            if (!$tierResult['tier']) {
                throw new QuotaExceededException(
                    'All quotas exhausted. Please upgrade or wait.',
                    $tierResult['retry_after'] ?? 3600
                );
            }

            $tier = $tierResult['tier'];

            // Apply admission control (clamp tokens)
            $payload = $this->admissionControl->clamp($payload, $tier);

            // Check cache for deterministic requests
            if ($this->cacheService->isCacheable($payload)) {
                $cacheKey = $this->cacheService->generateKey($payload, $tier);
                $cached = $this->cacheService->get($cacheKey);

                if ($cached) {
                    $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                    // Log cache hit
                    $this->telemetryService->logRequest(
                        requestId: $requestId,
                        user: $user,
                        project: $project,
                        apiKey: $apiKey,
                        tier: $tier,
                        usage: $cached['usage'] ?? [],
                        latencyMs: $latencyMs,
                        isCached: true
                    );

                    $cached['_cached'] = true;
                    return $cached;
                }
            }

            // Estimate tokens for pre-authorization
            $estimatedTokens = $this->quotaService->estimateTokens($payload['messages'] ?? []);
            $estimatedTotal = $estimatedTokens + ($payload['max_tokens'] ?? 500);

            // Pre-authorize quota
            if (!$this->quotaService->preAuthorize($user, $tier, $estimatedTotal)) {
                // Try fallback tier
                $fallbackTier = $this->getFallbackTier($tier);
                
                if ($fallbackTier && $this->quotaService->preAuthorize($user, $fallbackTier, $estimatedTotal)) {
                    $tier = $fallbackTier;
                    $payload = $this->admissionControl->clamp($payload, $tier);
                } else {
                    throw new QuotaExceededException('Quota limit reached');
                }
            }

            // Send to LiteLLM
            $response = $this->client->chatCompletion($payload, $tier, $requestId);

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);
            $usage = $response['usage'] ?? [];

            // Post-adjust quota with actual usage
            $actualTokens = $usage['total_tokens'] ?? 0;
            $this->quotaService->postAdjust($user, $tier, $estimatedTotal, $actualTokens);

            // Update DB quota
            $this->quotaService->updateDbQuota(
                $user,
                $tier,
                $actualTokens,
                $usage['prompt_tokens'] ?? 0,
                $usage['completion_tokens'] ?? 0
            );

            // Cache if deterministic
            if ($this->cacheService->isCacheable($payload)) {
                $this->cacheService->put($cacheKey ?? '', $response);
            }

            // Log telemetry
            $this->telemetryService->logRequest(
                requestId: $requestId,
                user: $user,
                project: $project,
                apiKey: $apiKey,
                tier: $tier,
                usage: $usage,
                latencyMs: $latencyMs
            );

            // Add metadata to response
            $response['_meta'] = [
                'tier' => $tier,
                'tier_reason' => $tierResult['reason'],
                'latency_ms' => $latencyMs,
            ];

            return $response;

        } catch (LlmException $e) {
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->telemetryService->logError(
                requestId: $requestId,
                user: $user,
                project: $project,
                apiKey: $apiKey,
                tier: $tier ?? 'unknown',
                errorType: $e->getErrorType(),
                statusCode: $e->getHttpStatus(),
                latencyMs: $latencyMs
            );

            throw $e;
        }
    }

    /**
     * Process streaming chat completion.
     */
    public function chatCompletionStream(
        array $payload,
        User $user,
        Project $project,
        ?ProjectApiKey $apiKey,
        array $planConfig,
        string $requestId,
        ?string $requestedTier = null
    ): \Generator {
        $startTime = microtime(true);

        // Select tier
        $tierResult = $this->tierSelector->selectTier($user, $planConfig, $requestedTier);

        if (!$tierResult['tier']) {
            throw new QuotaExceededException(
                'All quotas exhausted',
                $tierResult['retry_after'] ?? 3600
            );
        }

        $tier = $tierResult['tier'];

        // Apply admission control
        $payload = $this->admissionControl->clamp($payload, $tier);
        $payload['stream'] = true;

        // Pre-authorize
        $estimatedTokens = $this->quotaService->estimateTokens($payload['messages'] ?? []);
        $estimatedTotal = $estimatedTokens + ($payload['max_tokens'] ?? 500);

        if (!$this->quotaService->preAuthorize($user, $tier, $estimatedTotal)) {
            throw new QuotaExceededException('Quota limit reached');
        }

        $totalUsage = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
        ];
        $timeToFirstToken = null;
        $originalTier = $tier;

        try {
            foreach ($this->client->chatCompletionStream($payload, $tier, $requestId) as $chunkData) {
                $chunk = $chunkData['chunk'];
                
                // Double-check for errors (safety net in case LiteLlmClient missed something)
                if (isset($chunk['error']) || isset($chunk['details'])) {
                    // Use LiteLlmClient's extractErrorMessage method for consistent error parsing
                    $errorMessage = $this->extractErrorMessageFromChunk($chunk);
                    
                    Log::error('GatewayService detected error chunk that was not caught', [
                        'request_id' => $requestId,
                        'tier' => $tier,
                        'chunk' => $chunk,
                        'extracted_message' => $errorMessage,
                    ]);
                    
                    throw new \App\Exceptions\Llm\ProviderException($errorMessage, 502);
                }
                
                if ($timeToFirstToken === null) {
                    $timeToFirstToken = $chunkData['time_to_first_token_ms'];
                }

                // Accumulate usage if present
                if (isset($chunk['usage'])) {
                    $totalUsage['prompt_tokens'] = $chunk['usage']['prompt_tokens'] ?? 0;
                    $totalUsage['completion_tokens'] = $chunk['usage']['completion_tokens'] ?? 0;
                    $totalUsage['total_tokens'] = $chunk['usage']['total_tokens'] ?? 0;
                }

                yield $chunk;
            }

        } catch (\App\Exceptions\Llm\ProviderException $e) {
            // Try fallback tier if provider error
            $fallbackTier = $this->getFallbackTier($tier);
            
            if ($fallbackTier && $tier !== $fallbackTier) {
                Log::warning('Streaming failed, trying fallback tier', [
                    'request_id' => $requestId,
                    'original_tier' => $tier,
                    'fallback_tier' => $fallbackTier,
                    'error' => $e->getMessage(),
                ]);

                // Rollback pre-authorization for original tier
                $this->quotaService->postAdjust($user, $tier, $estimatedTotal, 0);

                // Try fallback tier
                $tier = $fallbackTier;
                $payload = $this->admissionControl->clamp($payload, $tier);
                
                if ($this->quotaService->preAuthorize($user, $tier, $estimatedTotal)) {
                    // Reset usage tracking
                    $totalUsage = [
                        'prompt_tokens' => 0,
                        'completion_tokens' => 0,
                        'total_tokens' => 0,
                    ];
                    $timeToFirstToken = null;

                    // Retry with fallback
                    foreach ($this->client->chatCompletionStream($payload, $tier, $requestId) as $chunkData) {
                        $chunk = $chunkData['chunk'];
                        
                        if ($timeToFirstToken === null) {
                            $timeToFirstToken = $chunkData['time_to_first_token_ms'];
                        }

                        if (isset($chunk['usage']) && is_array($chunk['usage'])) {
                            $totalUsage['prompt_tokens'] = $chunk['usage']['prompt_tokens'] ?? 0;
                            $totalUsage['completion_tokens'] = $chunk['usage']['completion_tokens'] ?? 0;
                            $totalUsage['total_tokens'] = $chunk['usage']['total_tokens'] ?? 0;
                        }

                        yield $chunk;
                    }
                } else {
                    // Fallback tier also exhausted, throw original error
                    throw $e;
                }
            } else {
                // No fallback available, throw error
                throw $e;
            }

        } finally {
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            // Post-adjust quota
            $actualTokens = $totalUsage['total_tokens'];
            $this->quotaService->postAdjust($user, $tier, $estimatedTotal, $actualTokens);

            // Update DB quota
            $this->quotaService->updateDbQuota(
                $user,
                $tier,
                $actualTokens,
                $totalUsage['prompt_tokens'],
                $totalUsage['completion_tokens']
            );

            // Log telemetry
            $this->telemetryService->logRequest(
                requestId: $requestId,
                user: $user,
                project: $project,
                apiKey: $apiKey,
                tier: $tier,
                usage: $totalUsage,
                latencyMs: $latencyMs,
                timeToFirstTokenMs: $timeToFirstToken,
                isStreaming: true
            );
        }
    }

    /**
     * Check if request should trigger decompose pipeline.
     */
    protected function shouldDecompose(array $payload, bool $force): bool
    {
        if ($force) {
            return true;
        }

        $triggers = config('codexflow.decompose.triggers');
        $messages = $payload['messages'] ?? [];

        // Estimate input tokens
        $estimatedTokens = $this->quotaService->estimateTokens($messages);
        if ($estimatedTokens >= $triggers['min_input_tokens']) {
            return true;
        }

        // Check character length
        $totalChars = 0;
        foreach ($messages as $message) {
            $totalChars += strlen($message['content'] ?? '');
        }

        if ($totalChars >= $triggers['min_char_length']) {
            return true;
        }

        return false;
    }

    /**
     * Get fallback tier.
     */
    protected function getFallbackTier(string $tier): ?string
    {
        return match ($tier) {
            'deep' => 'fast',
            'fast' => 'grace',
            default => null,
        };
    }

    /**
     * Extract error message from chunk (similar to LiteLlmClient::extractErrorMessage).
     */
    protected function extractErrorMessageFromChunk(array $chunk): string
    {
        // Format 1: Standard OpenAI format
        if (isset($chunk['error']['message'])) {
            return $chunk['error']['message'];
        }

        // Format 2: LiteLLM format with details (ERROR_OPENAI, ERROR_ANTHROPIC, etc.)
        if (isset($chunk['error']) && isset($chunk['details'])) {
            $errorType = is_string($chunk['error']) ? $chunk['error'] : null;
            $detail = $chunk['details'];
            
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
                            
                            if ($errorJson && isset($errorJson['message'])) {
                                $message = $errorJson['message'];
                                // If it's "Server Error", provide more context
                                if (strtolower(trim($message)) === 'server error') {
                                    $providerHint = $errorType === 'ERROR_OPENAI' 
                                        ? 'OpenAI API may be experiencing issues or API key is invalid. ' 
                                        : 'The model provider may be experiencing issues. ';
                                    return ($title ?: 'Unable to reach the model provider') . ': ' . 
                                           $providerHint . 'Please check your API keys and try again later.';
                                }
                                return $message;
                            }
                        }
                    }
                    
                    // Check if detailText contains "Server Error" directly
                    if (preg_match('/Server Error/i', $detailText)) {
                        $providerHint = $errorType === 'ERROR_OPENAI' 
                            ? 'OpenAI API returned a server error. This may be temporary. ' 
                            : 'The model provider returned a server error. ';
                        return ($title ?: 'Server Error') . ': ' . 
                               $providerHint . 'Please try again in a moment or check your API key configuration.';
                    }
                    
                    // Try simpler pattern: "message": "..."
                    if (preg_match('/"message"\s*:\s*"([^"]+)"/', $detailText, $matches)) {
                        return $matches[1];
                    }
                    
                    // Build message from title and detail
                    if ($title && $detailText) {
                        if ($errorType && str_starts_with($errorType, 'ERROR_')) {
                            if ($errorType === 'ERROR_OPENAI') {
                                $hint = 'This usually indicates an issue with OpenAI API (invalid key, rate limit, or service outage). ';
                                return "{$title}: {$hint}" . trim($detailText);
                            }
                            return "{$title} ({$errorType}): {$detailText}";
                        }
                        return "{$title}: {$detailText}";
                    }
                    
                    return $title ?: $detailText;
                }
                
                if ($title) {
                    return $errorType && str_starts_with($errorType, 'ERROR_') 
                        ? "{$title} ({$errorType})" 
                        : $title;
                }
            }
        }

        // Format 3: Direct error string (ERROR_OPENAI, ERROR_ANTHROPIC, etc.)
        if (isset($chunk['error']) && is_string($chunk['error'])) {
            $errorStr = $chunk['error'];
            if (str_starts_with($errorStr, 'ERROR_')) {
                $providerName = str_replace('ERROR_', '', $errorStr);
                return "Provider error ({$providerName}): Unable to reach the model provider. Please check your API key configuration.";
            }
            return $errorStr;
        }

        // Format 4: Details only
        if (isset($chunk['details']['detail'])) {
            return $chunk['details']['detail'];
        }

        if (isset($chunk['details']['title'])) {
            return $chunk['details']['title'];
        }

        return 'Unknown error from LLM provider';
    }
}


