<?php

namespace App\Services\Llm\Quality;

use App\Services\Llm\Pipeline\PipelineContext;
use App\Exceptions\Llm\QualityGateFailedException;

/**
 * Enforces quality gates at various pipeline stages.
 */
class QualityGateEnforcer
{
    /**
     * Check a specific quality gate
     * 
     * @return int Returns must_fix count for review gate, 0 for others
     * @throws QualityGateFailedException
     */
    public function check(string $gate, PipelineContext $context): int
    {
        return match($gate) {
            'plan_required' => $this->checkPlanRequired($context),
            'patch_only' => $this->checkPatchOnly($context),
            'must_fix_zero' => $this->checkMustFixZero($context),
            'tests_required' => $this->checkTestsRequired($context),
            'safety_gate' => $this->checkSafetyGate($context),
            default => 0,
        };
    }
    
    /**
     * Gate 1: Plan Required
     */
    protected function checkPlanRequired(PipelineContext $context): int
    {
        if (empty($context->plan) || empty($context->plan['steps'])) {
            throw new QualityGateFailedException(
                'plan_required',
                'Plan generation failed - no valid step plan produced'
            );
        }
        
        return 0;
    }
    
    /**
     * Gate 2: Patch Only (unified diff format)
     */
    protected function checkPatchOnly(PipelineContext $context): int
    {
        $code = $context->code ?? '';
        
        if (empty($code)) {
            throw new QualityGateFailedException(
                'patch_only',
                'No code output produced'
            );
        }
        
        // Check for unified diff indicators
        $hasDiffMarkers = str_contains($code, '---') && str_contains($code, '+++');
        $hasHunkHeaders = preg_match('/@@\s*-\d+,?\d*\s*\+\d+,?\d*\s*@@/', $code);
        
        if (!$hasDiffMarkers && !$hasHunkHeaders) {
            // Not a valid diff - this triggers retry
            throw new QualityGateFailedException(
                'patch_only',
                'Output is not in unified diff format. Retry with strict prompt.',
                true // retriable
            );
        }
        
        return 0;
    }
    
    /**
     * Gate 3: Must Fix Zero
     */
    protected function checkMustFixZero(PipelineContext $context): int
    {
        $mustFixCount = $context->getMustFixCount();
        
        // Return count instead of throwing - rework loop handles this
        return $mustFixCount;
    }
    
    /**
     * Gate 4: Tests Required (for medium+ risk)
     */
    protected function checkTestsRequired(PipelineContext $context): int
    {
        // Only enforce for medium+ risk
        if (!$context->requiresTests()) {
            return 0;
        }
        
        $tests = $context->tests ?? '';
        
        if (empty($tests)) {
            throw new QualityGateFailedException(
                'tests_required',
                'Tests are required for medium/high/critical risk tasks',
                true // retriable - send to test agent
            );
        }
        
        // Check for test content
        $hasTestClass = str_contains($tests, 'class') && str_contains($tests, 'Test');
        $hasTestMethod = str_contains($tests, 'function test_') || str_contains($tests, 'public function test');
        
        if (!$hasTestClass && !$hasTestMethod) {
            throw new QualityGateFailedException(
                'tests_required',
                'Generated tests do not contain valid test classes or methods',
                true
            );
        }
        
        return 0;
    }
    
    /**
     * Gate 5: Safety Gate (for sensitive domains)
     */
    protected function checkSafetyGate(PipelineContext $context): int
    {
        // Only enforce for sensitive domains
        if (!$context->hasSensitiveDomain()) {
            return 0;
        }
        
        $review = $context->review ?? [];
        
        // Require risk_notes for sensitive domains
        if (empty($review['risk_notes'])) {
            throw new QualityGateFailedException(
                'safety_gate',
                'Sensitive domain changes require risk_notes in review',
                true // retriable - send back to review
            );
        }
        
        // Require test_gaps acknowledgment
        if (!isset($review['test_gaps'])) {
            throw new QualityGateFailedException(
                'safety_gate',
                'Sensitive domain changes require test_gaps section in review',
                true
            );
        }
        
        // For webhooks, require idempotency notes
        $domains = $context->getDomains();
        if (in_array('webhooks', $domains)) {
            $hasIdempotencyNote = false;
            foreach ($review['risk_notes'] as $note) {
                if (stripos($note, 'idempoten') !== false) {
                    $hasIdempotencyNote = true;
                    break;
                }
            }
            
            if (!$hasIdempotencyNote) {
                throw new QualityGateFailedException(
                    'safety_gate',
                    'Webhook changes require idempotency strategy in risk_notes',
                    true
                );
            }
        }
        
        return 0;
    }
    
    /**
     * Get all configured quality gates
     */
    public function getAllGates(): array
    {
        return config('codexflow.pipeline.quality_gates', []);
    }
}

