<?php

namespace App\Services\Llm\Pipeline;

/**
 * Result object returned from pipeline execution.
 */
class PipelineResult
{
    public bool $success;
    public string $status; // 'done', 'rework_limit', 'error'
    public PipelineContext $context;
    public ?string $error = null;
    
    public function __construct(
        PipelineContext $context,
        bool $success = true,
        string $status = 'done',
        ?string $error = null
    ) {
        $this->context = $context;
        $this->success = $success;
        $this->status = $status;
        $this->error = $error;
    }
    
    /**
     * Get the final code output (unified diff)
     */
    public function getCode(): ?string
    {
        return $this->context->code;
    }
    
    /**
     * Get the tests output
     */
    public function getTests(): ?string
    {
        return $this->context->tests;
    }
    
    /**
     * Get the review checklist
     */
    public function getReview(): ?array
    {
        return $this->context->review;
    }
    
    /**
     * Get total cost in USD
     */
    public function getTotalCostUsd(): float
    {
        return $this->context->totalCostUsd;
    }
    
    /**
     * Get all stage metrics
     */
    public function getStageMetrics(): array
    {
        return $this->context->stageMetrics;
    }
    
    /**
     * Get summary for logging/response
     */
    public function getSummary(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'request_id' => $this->context->requestId,
            'task_type' => $this->context->getTaskType(),
            'risk' => $this->context->getRisk(),
            'budget_class' => $this->context->getBudgetClass(),
            'rework_count' => $this->context->getReworkCount(),
            'total_cost_usd' => $this->getTotalCostUsd(),
            'total_tokens_in' => $this->context->totalTokensIn,
            'total_tokens_out' => $this->context->totalTokensOut,
            'stages_completed' => array_keys($this->context->stageMetrics),
            'error' => $this->error,
        ];
    }
    
    /**
     * Format as API response
     */
    public function toApiResponse(): array
    {
        if (!$this->success) {
            return [
                'success' => false,
                'error' => $this->error ?? 'Pipeline execution failed',
                'status' => $this->status,
            ];
        }
        
        return [
            'success' => true,
            'status' => $this->status,
            'code' => $this->getCode(),
            'tests' => $this->getTests(),
            'review' => $this->getReview(),
            'metrics' => [
                'total_cost_usd' => $this->getTotalCostUsd(),
                'rework_count' => $this->context->getReworkCount(),
                'stages' => array_keys($this->context->stageMetrics),
            ],
        ];
    }
}

