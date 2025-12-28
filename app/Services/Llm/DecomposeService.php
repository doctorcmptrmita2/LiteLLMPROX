<?php

namespace App\Services\Llm;

use App\Exceptions\Llm\ProviderException;
use App\Exceptions\Llm\TimeoutException;
use App\Models\Project;
use App\Models\ProjectApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DecomposeService
{
    protected const MAX_CHUNKS = 3;
    protected const MAX_TOTAL_CALLS = 4;
    protected const TOTAL_TIMEOUT_SECONDS = 480;

    protected string $plannerSystemPrompt = <<<'PROMPT'
You are a code planning assistant. Your ONLY job is to analyze a large coding request and break it into 2-3 smaller, focused chunks.

RULES:
1. Output ONLY valid JSON, no prose before or after
2. Each chunk should handle a specific, cohesive part of the work
3. Maximum 5 files per chunk
4. Use "fast" tier for simple tasks (models, config, migrations)
5. Use "deep" tier ONLY for complex logic (gateways, pipelines, services)
6. Keep deep to minimum (ideally 1 chunk only)

JSON Schema:
{
  "summary": ["short description line 1", "line 2"],
  "chunks": [
    {
      "id": "A",
      "title": "chunk title",
      "goal": "what this chunk achieves",
      "files": ["path/to/file1.php", "path/to/file2.php"],
      "tier": "fast|deep",
      "max_output_tokens": 700
    }
  ],
  "execution_order": "parallel|sequential"
}
PROMPT;

    public function __construct(
        protected LiteLlmClient $client,
        protected QuotaService $quotaService,
        protected TelemetryService $telemetryService
    ) {}

    /**
     * Process large request through decompose pipeline.
     */
    public function process(
        array $payload,
        User $user,
        Project $project,
        ?ProjectApiKey $apiKey,
        array $planConfig,
        string $requestId
    ): array {
        $startTime = microtime(true);
        $chunks = [];
        $results = [];

        try {
            // Step 1: Call planner to get chunk plan
            $plan = $this->callPlanner($payload, $requestId);

            if (!$plan || empty($plan['chunks'])) {
                throw new ProviderException('Planner failed to generate valid plan');
            }

            // Limit chunks
            $chunksToProcess = array_slice($plan['chunks'], 0, self::MAX_CHUNKS);

            // Log planner call
            $this->telemetryService->logRequest(
                requestId: $requestId,
                user: $user,
                project: $project,
                apiKey: $apiKey,
                tier: 'planner',
                usage: ['total_tokens' => 500], // Estimated
                latencyMs: 0,
                isDecomposed: true,
                chunkIndex: 0
            );

            // Step 2: Execute each chunk
            foreach ($chunksToProcess as $index => $chunk) {
                $chunkResult = $this->executeChunk(
                    originalMessages: $payload['messages'] ?? [],
                    chunk: $chunk,
                    requestId: $requestId,
                    chunkIndex: $index + 1
                );

                $results[] = [
                    'chunk_id' => $chunk['id'] ?? chr(65 + $index),
                    'title' => $chunk['title'] ?? 'Chunk ' . ($index + 1),
                    'tier' => $chunk['tier'] ?? 'fast',
                    'content' => $chunkResult['content'] ?? '',
                    'usage' => $chunkResult['usage'] ?? [],
                ];

                // Log chunk
                $this->telemetryService->logRequest(
                    requestId: $requestId,
                    user: $user,
                    project: $project,
                    apiKey: $apiKey,
                    tier: $chunk['tier'] ?? 'fast',
                    usage: $chunkResult['usage'] ?? [],
                    latencyMs: $chunkResult['latency_ms'] ?? 0,
                    isDecomposed: true,
                    parentRequestId: $requestId,
                    chunkIndex: $index + 1
                );

                // Update quota
                $usage = $chunkResult['usage'] ?? [];
                $this->quotaService->updateDbQuota(
                    $user,
                    $chunk['tier'] ?? 'fast',
                    $usage['total_tokens'] ?? 0,
                    $usage['prompt_tokens'] ?? 0,
                    $usage['completion_tokens'] ?? 0
                );

                // Check total timeout
                if ((microtime(true) - $startTime) > self::TOTAL_TIMEOUT_SECONDS) {
                    throw new TimeoutException('Decompose pipeline timeout exceeded');
                }
            }

            // Step 3: Combine results
            $combinedContent = $this->combineResults($plan, $results);

            $totalLatencyMs = (int) ((microtime(true) - $startTime) * 1000);
            $totalUsage = $this->aggregateUsage($results);

            return [
                'id' => 'decompose-' . $requestId,
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'cf-decompose',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => $combinedContent,
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => $totalUsage,
                '_meta' => [
                    'decomposed' => true,
                    'chunks' => count($results),
                    'latency_ms' => $totalLatencyMs,
                    'plan' => $plan['summary'] ?? [],
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Decompose pipeline failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Call planner to generate chunk plan.
     */
    protected function callPlanner(array $originalPayload, string $requestId): ?array
    {
        $plannerPayload = [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->plannerSystemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $this->extractPlannerContext($originalPayload['messages'] ?? []),
                ],
            ],
            'max_tokens' => config('codexflow.decompose.planner.max_output_tokens', 500),
            'temperature' => 0,
        ];

        try {
            $response = $this->client->chatCompletion($plannerPayload, 'planner', $requestId . '-planner');

            $content = $response['choices'][0]['message']['content'] ?? '';

            // Parse JSON from response
            return $this->parseJson($content);

        } catch (\Exception $e) {
            Log::warning('Planner call failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            // Return default plan
            return $this->getDefaultPlan();
        }
    }

    /**
     * Execute a single chunk.
     */
    protected function executeChunk(
        array $originalMessages,
        array $chunk,
        string $requestId,
        int $chunkIndex
    ): array {
        $tier = $chunk['tier'] ?? 'fast';
        $maxTokens = $chunk['max_output_tokens'] ?? 
            config("codexflow.decompose.chunk_limits.{$tier}.max_output_tokens", 700);

        // Build chunk-specific prompt
        $chunkPrompt = $this->buildChunkPrompt($originalMessages, $chunk);

        $payload = [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You are implementing part of a larger task. Focus ONLY on: {$chunk['goal']}\n\nTarget files: " . implode(', ', $chunk['files'] ?? []) . "\n\nOutput ONLY the code changes needed. Use unified diff format when modifying existing files.",
                ],
                [
                    'role' => 'user',
                    'content' => $chunkPrompt,
                ],
            ],
            'max_tokens' => $maxTokens,
            'temperature' => 0,
        ];

        $startTime = microtime(true);

        try {
            $response = $this->client->chatCompletion(
                $payload,
                $tier,
                "{$requestId}-chunk-{$chunkIndex}"
            );

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'content' => $response['choices'][0]['message']['content'] ?? '',
                'usage' => $response['usage'] ?? [],
                'latency_ms' => $latencyMs,
            ];

        } catch (\Exception $e) {
            Log::warning('Chunk execution failed', [
                'request_id' => $requestId,
                'chunk_index' => $chunkIndex,
                'error' => $e->getMessage(),
            ]);

            return [
                'content' => "// Error: {$e->getMessage()}",
                'usage' => [],
                'latency_ms' => 0,
            ];
        }
    }

    /**
     * Build chunk-specific prompt from original messages.
     */
    protected function buildChunkPrompt(array $messages, array $chunk): string
    {
        // Extract relevant context from original messages
        $context = '';
        foreach ($messages as $msg) {
            if ($msg['role'] === 'user') {
                $context .= $msg['content'] . "\n\n";
            }
        }

        return "Original request context:\n{$context}\n\nYour task for this chunk:\n{$chunk['goal']}";
    }

    /**
     * Combine chunk results into final response.
     */
    protected function combineResults(array $plan, array $results): string
    {
        $output = "## DECOMPOSE PIPELINE RESULTS\n\n";

        // Add summary
        if (!empty($plan['summary'])) {
            $output .= "### Summary\n";
            foreach ($plan['summary'] as $line) {
                $output .= "- {$line}\n";
            }
            $output .= "\n";
        }

        // Add each chunk result
        foreach ($results as $result) {
            $output .= "### Chunk {$result['chunk_id']}: {$result['title']}\n";
            $output .= "*Tier: {$result['tier']}*\n\n";
            $output .= $result['content'] . "\n\n";
            $output .= "---\n\n";
        }

        return trim($output);
    }

    /**
     * Aggregate usage from all chunks.
     */
    protected function aggregateUsage(array $results): array
    {
        $total = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
        ];

        foreach ($results as $result) {
            $usage = $result['usage'] ?? [];
            $total['prompt_tokens'] += $usage['prompt_tokens'] ?? 0;
            $total['completion_tokens'] += $usage['completion_tokens'] ?? 0;
            $total['total_tokens'] += $usage['total_tokens'] ?? 0;
        }

        return $total;
    }

    /**
     * Extract context for planner.
     */
    protected function extractPlannerContext(array $messages): string
    {
        $context = "Analyze this coding request and create a chunk plan:\n\n";

        foreach ($messages as $msg) {
            $role = strtoupper($msg['role'] ?? 'user');
            $content = $msg['content'] ?? '';
            
            // Truncate very long messages
            if (strlen($content) > 8000) {
                $content = substr($content, 0, 8000) . "\n\n[TRUNCATED...]";
            }

            $context .= "[{$role}]\n{$content}\n\n";
        }

        return $context;
    }

    /**
     * Parse JSON from potentially messy response.
     */
    protected function parseJson(string $content): ?array
    {
        // Try direct parse
        $decoded = json_decode($content, true);
        if ($decoded) {
            return $decoded;
        }

        // Try to extract JSON from markdown code block
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*\})\s*```/', $content, $matches)) {
            return json_decode($matches[1], true);
        }

        // Try to find JSON object
        if (preg_match('/\{[\s\S]*"chunks"[\s\S]*\}/', $content, $matches)) {
            return json_decode($matches[0], true);
        }

        return null;
    }

    /**
     * Get default plan when planner fails.
     */
    protected function getDefaultPlan(): array
    {
        return [
            'summary' => ['Default decompose plan'],
            'chunks' => [
                [
                    'id' => 'A',
                    'title' => 'Main implementation',
                    'goal' => 'Complete the requested task',
                    'files' => [],
                    'tier' => 'deep',
                    'max_output_tokens' => 1200,
                ],
            ],
            'execution_order' => 'sequential',
        ];
    }
}



