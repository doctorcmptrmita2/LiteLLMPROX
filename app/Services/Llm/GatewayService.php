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

        try {
            foreach ($this->client->chatCompletionStream($payload, $tier, $requestId) as $chunkData) {
                $chunk = $chunkData['chunk'];
                
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
}


