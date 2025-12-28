<?php

namespace Tests\Feature\Pipeline;

use Tests\TestCase;
use App\Services\Llm\Routing\RiskScorer;

class RiskScorerTest extends TestCase
{
    private RiskScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new RiskScorer();
    }

    public function test_scores_auth_domain_as_critical(): void
    {
        $result = $this->scorer->score([
            'files_estimate' => 1,
            'domains' => ['auth'],
            'task_type' => 'bugfix'
        ]);

        $this->assertEquals('critical', $result);
    }

    public function test_scores_billing_domain_as_critical(): void
    {
        $result = $this->scorer->score([
            'files_estimate' => 1,
            'domains' => ['billing'],
            'task_type' => 'bugfix'
        ]);

        $this->assertEquals('critical', $result);
    }

    public function test_scores_webhook_domain_as_critical(): void
    {
        $result = $this->scorer->score([
            'files_estimate' => 1,
            'domains' => ['webhooks'],
            'task_type' => 'bugfix'
        ]);

        $this->assertEquals('critical', $result);
    }

    public function test_scores_simple_task_as_low(): void
    {
        $result = $this->scorer->score([
            'files_estimate' => 1,
            'domains' => [],
            'task_type' => 'bugfix'
        ]);

        $this->assertEquals('low', $result);
    }

    public function test_scores_queue_domain_as_high(): void
    {
        $result = $this->scorer->score([
            'files_estimate' => 1,
            'domains' => ['queue'],
            'task_type' => 'bugfix'
        ]);

        $this->assertEquals('high', $result);
    }

    public function test_scores_many_files_as_high(): void
    {
        $result = $this->scorer->score([
            'files_estimate' => 4,
            'domains' => [],
            'task_type' => 'bugfix'
        ]);

        $this->assertEquals('high', $result);
    }

    public function test_scores_two_files_as_medium(): void
    {
        $result = $this->scorer->score([
            'files_estimate' => 2,
            'domains' => [],
            'task_type' => 'bugfix'
        ]);

        $this->assertEquals('medium', $result);
    }

    public function test_scores_refactor_as_medium(): void
    {
        $result = $this->scorer->score([
            'files_estimate' => 1,
            'domains' => [],
            'task_type' => 'refactor'
        ]);

        $this->assertEquals('medium', $result);
    }

    public function test_requires_reasoning_for_high_risk(): void
    {
        $result = $this->scorer->requiresReasoning([
            'risk' => 'high',
            'files_estimate' => 3,
            'domains' => [],
            'task_type' => 'feature'
        ]);

        $this->assertTrue($result);
    }

    public function test_does_not_require_reasoning_for_low_risk(): void
    {
        $result = $this->scorer->requiresReasoning([
            'risk' => 'low',
            'files_estimate' => 1,
            'domains' => [],
            'task_type' => 'bugfix'
        ]);

        $this->assertFalse($result);
    }

    public function test_returns_premium_for_high_risk_review(): void
    {
        $result = $this->scorer->getReviewModel('high');
        $this->assertEquals('cf-premium-coder', $result);
    }

    public function test_returns_budget_for_low_risk_review(): void
    {
        $result = $this->scorer->getReviewModel('low');
        $this->assertEquals('cf-budget-reviewer', $result);
    }
}

