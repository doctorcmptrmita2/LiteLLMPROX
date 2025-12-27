<?php

namespace App\Services\Llm;

use App\Models\LlmRequest;
use App\Models\Project;
use App\Models\ProjectApiKey;
use App\Models\User;

class TelemetryService
{
    /**
     * Log a completed LLM request.
     */
    public function logRequest(
        string $requestId,
        User $user,
        Project $project,
        ?ProjectApiKey $apiKey,
        string $tier,
        array $usage,
        int $latencyMs,
        ?int $timeToFirstTokenMs = null,
        bool $isCached = false,
        bool $isStreaming = false,
        bool $isDecomposed = false,
        ?string $parentRequestId = null,
        ?int $chunkIndex = null,
        ?int $statusCode = 200,
        ?string $errorType = null
    ): LlmRequest {
        $costs = config("codexflow.costs.{$tier}", ['input' => 0, 'output' => 0]);
        
        $inputCost = ($usage['prompt_tokens'] ?? 0) / 1_000_000 * $costs['input'];
        $outputCost = ($usage['completion_tokens'] ?? 0) / 1_000_000 * $costs['output'];
        $totalCost = round($inputCost + $outputCost, 6);

        return LlmRequest::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'api_key_id' => $apiKey?->id,
            'parent_request_id' => $parentRequestId,
            'chunk_index' => $chunkIndex,
            'request_id' => $requestId,
            'tier' => $tier,
            'model_alias' => config("litellm.aliases.{$tier}"),
            'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
            'total_tokens' => $usage['total_tokens'] ?? 0,
            'cost_usd' => $totalCost,
            'latency_ms' => $latencyMs,
            'time_to_first_token_ms' => $timeToFirstTokenMs,
            'is_cached' => $isCached,
            'is_streaming' => $isStreaming,
            'is_decomposed' => $isDecomposed,
            'status_code' => $statusCode,
            'error_type' => $errorType,
        ]);
    }

    /**
     * Log an error request.
     */
    public function logError(
        string $requestId,
        User $user,
        Project $project,
        ?ProjectApiKey $apiKey,
        string $tier,
        string $errorType,
        int $statusCode,
        int $latencyMs
    ): LlmRequest {
        return $this->logRequest(
            requestId: $requestId,
            user: $user,
            project: $project,
            apiKey: $apiKey,
            tier: $tier,
            usage: [],
            latencyMs: $latencyMs,
            statusCode: $statusCode,
            errorType: $errorType
        );
    }
}

