<?php

namespace Tests\Feature\Pipeline;

use Tests\TestCase;
use App\Services\Llm\Routing\BudgetClassifier;

class BudgetClassifierTest extends TestCase
{
    private BudgetClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new BudgetClassifier();
    }

    public function test_returns_premium_for_high_risk(): void
    {
        $result = $this->classifier->classify([
            'risk' => 'high',
            'files_estimate' => 2,
            'domains' => []
        ]);

        $this->assertEquals('premium', $result);
    }

    public function test_returns_premium_for_critical_risk(): void
    {
        $result = $this->classifier->classify([
            'risk' => 'critical',
            'files_estimate' => 1,
            'domains' => []
        ]);

        $this->assertEquals('premium', $result);
    }

    public function test_returns_balanced_for_medium_risk(): void
    {
        $result = $this->classifier->classify([
            'risk' => 'medium',
            'files_estimate' => 2,
            'domains' => []
        ]);

        $this->assertEquals('balanced', $result);
    }

    public function test_returns_cheap_for_low_risk_single_file(): void
    {
        $result = $this->classifier->classify([
            'risk' => 'low',
            'files_estimate' => 1,
            'domains' => []
        ]);

        $this->assertEquals('cheap', $result);
    }

    public function test_escalates_to_balanced_for_multiple_files(): void
    {
        $result = $this->classifier->classify([
            'risk' => 'low',
            'files_estimate' => 3,
            'domains' => []
        ]);

        $this->assertEquals('balanced', $result);
    }

    public function test_returns_correct_model_for_cheap(): void
    {
        $result = $this->classifier->getModelAlias('cheap');
        $this->assertEquals('cf-cheap-coder', $result);
    }

    public function test_returns_correct_model_for_balanced(): void
    {
        $result = $this->classifier->getModelAlias('balanced');
        $this->assertEquals('cf-balanced-coder', $result);
    }

    public function test_returns_correct_model_for_premium(): void
    {
        $result = $this->classifier->getModelAlias('premium');
        $this->assertEquals('cf-premium-coder', $result);
    }

    public function test_downgrades_premium_to_balanced(): void
    {
        $result = $this->classifier->downgrade('premium');
        $this->assertEquals('balanced', $result);
    }

    public function test_downgrades_balanced_to_cheap(): void
    {
        $result = $this->classifier->downgrade('balanced');
        $this->assertEquals('cheap', $result);
    }
}

