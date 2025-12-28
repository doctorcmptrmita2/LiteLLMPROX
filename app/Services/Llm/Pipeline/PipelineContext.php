<?php

namespace App\Services\Llm\Pipeline;

use App\Models\Project;
use App\Models\ProjectApiKey;
use Illuminate\Support\Collection;

/**
 * Context object that flows through the pipeline stages.
 * Collects data from each stage for use in subsequent stages.
 */
class PipelineContext
{
    public string $requestId;
    public Project $project;
    public ProjectApiKey $apiKey;
    public array $originalRequest;
    
    // Triage output
    public ?array $triage = null;
    
    // Plan output
    public ?array $plan = null;
    
    // Code output
    public ?string $code = null;
    public array $codePatches = [];
    
    // Review output
    public ?array $review = null;
    public Collection $reworkFeedback;
    
    // Test output
    public ?string $tests = null;
    
    // Final review output
    public ?array $finalReview = null;
    
    // Metrics
    public array $stageMetrics = [];
    public float $totalCostUsd = 0.0;
    public int $totalTokensIn = 0;
    public int $totalTokensOut = 0;
    
    public function __construct(
        string $requestId,
        Project $project,
        ProjectApiKey $apiKey,
        array $originalRequest
    ) {
        $this->requestId = $requestId;
        $this->project = $project;
        $this->apiKey = $apiKey;
        $this->originalRequest = $originalRequest;
        $this->reworkFeedback = collect();
    }
    
    /**
     * Set triage result from Triage Agent
     */
    public function setTriage(array $triage): self
    {
        $this->triage = $triage;
        return $this;
    }
    
    /**
     * Set plan result from Planner Agent
     */
    public function setPlan(array $plan): self
    {
        $this->plan = $plan;
        return $this;
    }
    
    /**
     * Set code result from Coding Agent
     */
    public function setCode(string $code): self
    {
        $this->code = $code;
        $this->codePatches[] = $code;
        return $this;
    }
    
    /**
     * Set review result from Review Agent
     */
    public function setReview(array $review): self
    {
        $this->review = $review;
        return $this;
    }
    
    /**
     * Add rework feedback for next coding iteration
     */
    public function addReworkFeedback(array $review): self
    {
        $this->reworkFeedback->push($review);
        return $this;
    }
    
    /**
     * Set test result from Test Agent
     */
    public function setTests(string $tests): self
    {
        $this->tests = $tests;
        return $this;
    }
    
    /**
     * Set final review result
     */
    public function setFinalReview(array $finalReview): self
    {
        $this->finalReview = $finalReview;
        return $this;
    }
    
    /**
     * Record metrics for a stage
     */
    public function recordStageMetrics(string $stage, array $metrics): self
    {
        $this->stageMetrics[$stage] = $metrics;
        
        $this->totalCostUsd += $metrics['cost_usd'] ?? 0;
        $this->totalTokensIn += $metrics['tokens_in'] ?? 0;
        $this->totalTokensOut += $metrics['tokens_out'] ?? 0;
        
        return $this;
    }
    
    /**
     * Get budget class from triage
     */
    public function getBudgetClass(): string
    {
        return $this->triage['budget_class'] ?? 'cheap';
    }
    
    /**
     * Get risk level from triage
     */
    public function getRisk(): string
    {
        return $this->triage['risk'] ?? 'low';
    }
    
    /**
     * Get task type from triage
     */
    public function getTaskType(): string
    {
        return $this->triage['task_type'] ?? 'bugfix';
    }
    
    /**
     * Get estimated file count from triage
     */
    public function getFilesEstimate(): int
    {
        return $this->triage['files_estimate'] ?? 1;
    }
    
    /**
     * Get domains from triage
     */
    public function getDomains(): array
    {
        return $this->triage['domains'] ?? [];
    }
    
    /**
     * Check if triage indicates simple path
     */
    public function isSimplePath(): bool
    {
        return $this->getBudgetClass() === 'cheap' 
            && $this->getFilesEstimate() <= 2 
            && $this->getRisk() === 'low';
    }
    
    /**
     * Check if tests are required based on risk
     */
    public function requiresTests(): bool
    {
        return in_array($this->getRisk(), ['medium', 'high', 'critical']);
    }
    
    /**
     * Check if domain is sensitive (requires safety gate)
     */
    public function hasSensitiveDomain(): bool
    {
        $sensitiveDomains = config('codexflow.budget.critical_domains', []);
        return count(array_intersect($this->getDomains(), $sensitiveDomains)) > 0;
    }
    
    /**
     * Get must_fix count from review
     */
    public function getMustFixCount(): int
    {
        return count($this->review['must_fix'] ?? []);
    }
    
    /**
     * Get rework iteration count
     */
    public function getReworkCount(): int
    {
        return $this->reworkFeedback->count();
    }
    
    /**
     * Get user messages from original request
     */
    public function getUserMessages(): array
    {
        return $this->originalRequest['messages'] ?? [];
    }
    
    /**
     * Get latest user message
     */
    public function getLatestUserMessage(): ?string
    {
        $messages = $this->getUserMessages();
        
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                return $messages[$i]['content'] ?? null;
            }
        }
        
        return null;
    }
}

