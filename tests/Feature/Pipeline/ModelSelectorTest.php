<?php

namespace Tests\Feature\Pipeline;

use Tests\TestCase;
use App\Services\Llm\Routing\ModelSelector;
use App\Services\Llm\Routing\BudgetClassifier;
use App\Services\Llm\Routing\RiskScorer;

class ModelSelectorTest extends TestCase
{
    private ModelSelector $selector;
    private BudgetClassifier $budgetClassifier;
    private RiskScorer $riskScorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->budgetClassifier = new BudgetClassifier();
        $this->riskScorer = new RiskScorer();
        $this->selector = new ModelSelector($this->budgetClassifier, $this->riskScorer);
    }

    public function test_returns_correct_model_for_cheap_budget(): void
    {
        $result = $this->budgetClassifier->getModelAlias('cheap');
        $this->assertEquals('cf-cheap-coder', $result);
    }

    public function test_returns_correct_model_for_balanced_budget(): void
    {
        $result = $this->budgetClassifier->getModelAlias('balanced');
        $this->assertEquals('cf-balanced-coder', $result);
    }

    public function test_returns_correct_model_for_premium_budget(): void
    {
        $result = $this->budgetClassifier->getModelAlias('premium');
        $this->assertEquals('cf-premium-coder', $result);
    }

    public function test_returns_base_models_for_cheap_budget(): void
    {
        $result = $this->selector->getAllowedModels('cheap');
        
        $this->assertContains('cf-triage', $result);
        $this->assertContains('cf-cheap-coder', $result);
        $this->assertContains('cf-budget-reviewer', $result);
        $this->assertContains('cf-grace', $result);
    }

    public function test_includes_balanced_coder_for_balanced_budget(): void
    {
        $result = $this->selector->getAllowedModels('balanced');
        
        $this->assertContains('cf-balanced-coder', $result);
        $this->assertContains('cf-oss-fallback', $result);
    }

    public function test_includes_all_coders_for_premium_budget(): void
    {
        $result = $this->selector->getAllowedModels('premium');
        
        $this->assertContains('cf-balanced-coder', $result);
        $this->assertContains('cf-premium-coder', $result);
        $this->assertContains('cf-planner', $result);
    }
}

