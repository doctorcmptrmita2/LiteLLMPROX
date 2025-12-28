<?php

namespace App\Services\Llm\Routing;

use App\Services\Llm\Pipeline\PipelineContext;

/**
 * Selects appropriate model for each pipeline stage.
 */
class ModelSelector
{
    protected BudgetClassifier $budgetClassifier;
    protected RiskScorer $riskScorer;
    
    public function __construct(
        BudgetClassifier $budgetClassifier,
        RiskScorer $riskScorer
    ) {
        $this->budgetClassifier = $budgetClassifier;
        $this->riskScorer = $riskScorer;
    }
    
    /**
     * Select model for a pipeline stage
     */
    public function selectForStage(string $stage, PipelineContext $context): string
    {
        return match($stage) {
            'triage' => 'cf-triage',
            'plan' => 'cf-planner',
            'code' => $this->selectCodingModel($context),
            'review' => $this->selectReviewModel($context),
            'test' => 'cf-cheap-coder',
            'final_review' => $this->selectReviewModel($context),
            default => 'cf-cheap-coder',
        };
    }
    
    /**
     * Select coding model based on budget class
     */
    public function selectCodingModel(PipelineContext $context): string
    {
        $budgetClass = $context->getBudgetClass();
        return $this->budgetClassifier->getModelAlias($budgetClass);
    }
    
    /**
     * Select review model based on risk
     */
    public function selectReviewModel(PipelineContext $context): string
    {
        return $this->riskScorer->getReviewModel($context->getRisk());
    }
    
    /**
     * Get stage configuration from config
     */
    public function getStageConfig(string $stage): array
    {
        return config("codexflow.pipeline.stages.{$stage}", [
            'model_alias' => 'cf-cheap-coder',
            'max_output_tokens' => 3000,
            'timeout' => 60,
        ]);
    }
    
    /**
     * Get allowed models for budget class
     */
    public function getAllowedModels(string $budgetClass): array
    {
        $costControl = config('codexflow.cost_control');
        
        $baseModels = [
            'cf-triage',
            'cf-cheap-coder',
            'cf-budget-reviewer',
            'cf-grace',
            'cf-grace-fallback',
        ];
        
        if ($budgetClass === 'balanced') {
            $baseModels[] = 'cf-balanced-coder';
            $baseModels[] = 'cf-oss-fallback';
        }
        
        if ($budgetClass === 'premium') {
            $baseModels[] = 'cf-balanced-coder';
            $baseModels[] = 'cf-premium-coder';
            $baseModels[] = 'cf-planner';
            $baseModels[] = 'cf-oss-fallback';
        }
        
        return $baseModels;
    }
}

