<?php

namespace App\Services\Llm\Pipeline;

use App\Models\Project;
use App\Models\ProjectApiKey;
use App\Services\Llm\Agents\TriageAgent;
use App\Services\Llm\Agents\PlannerAgent;
use App\Services\Llm\Agents\CodingAgent;
use App\Services\Llm\Agents\ReviewAgent;
use App\Services\Llm\Agents\TestAgent;
use App\Services\Llm\Quality\QualityGateEnforcer;
use App\Exceptions\Llm\QualityGateFailedException;
use App\Exceptions\Llm\ReworkLimitException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the role-based pipeline execution.
 */
class PipelineOrchestrator
{
    protected TriageAgent $triageAgent;
    protected PlannerAgent $plannerAgent;
    protected CodingAgent $codingAgent;
    protected ReviewAgent $reviewAgent;
    protected TestAgent $testAgent;
    protected QualityGateEnforcer $gateEnforcer;
    
    protected int $maxReworks;
    
    public function __construct(
        TriageAgent $triageAgent,
        PlannerAgent $plannerAgent,
        CodingAgent $codingAgent,
        ReviewAgent $reviewAgent,
        TestAgent $testAgent,
        QualityGateEnforcer $gateEnforcer
    ) {
        $this->triageAgent = $triageAgent;
        $this->plannerAgent = $plannerAgent;
        $this->codingAgent = $codingAgent;
        $this->reviewAgent = $reviewAgent;
        $this->testAgent = $testAgent;
        $this->gateEnforcer = $gateEnforcer;
        
        $this->maxReworks = config('codexflow.pipeline.rework.max_iterations', 3);
    }
    
    /**
     * Process a request through the pipeline
     */
    public function process(
        Project $project,
        ProjectApiKey $apiKey,
        array $request
    ): PipelineResult {
        $requestId = $request['request_id'] ?? Str::uuid()->toString();
        
        $context = new PipelineContext($requestId, $project, $apiKey, $request);
        
        try {
            // Stage 1: Triage
            $this->executeTriage($context);
            
            // Determine path
            if ($context->isSimplePath()) {
                return $this->executeSimplePath($context);
            }
            
            // Full pipeline
            return $this->executeFullPipeline($context);
            
        } catch (ReworkLimitException $e) {
            Log::warning('Pipeline rework limit reached', [
                'request_id' => $requestId,
                'rework_count' => $context->getReworkCount(),
            ]);
            
            return new PipelineResult($context, false, 'rework_limit', $e->getMessage());
            
        } catch (QualityGateFailedException $e) {
            Log::error('Pipeline quality gate failed', [
                'request_id' => $requestId,
                'gate' => $e->gate,
                'message' => $e->getMessage(),
            ]);
            
            return new PipelineResult($context, false, 'gate_failed', $e->getMessage());
            
        } catch (\Exception $e) {
            Log::error('Pipeline error', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            
            return new PipelineResult($context, false, 'error', $e->getMessage());
        }
    }
    
    /**
     * Execute triage stage
     */
    protected function executeTriage(PipelineContext $context): void
    {
        $startTime = microtime(true);
        
        $triage = $this->triageAgent->analyze($context->getUserMessages());
        $context->setTriage($triage);
        
        $context->recordStageMetrics('triage', [
            'duration_ms' => (microtime(true) - $startTime) * 1000,
            'task_type' => $triage['task_type'],
            'risk' => $triage['risk'],
            'budget_class' => $triage['budget_class'],
        ]);
        
        Log::info('Pipeline triage complete', [
            'request_id' => $context->requestId,
            'triage' => $triage,
        ]);
    }
    
    /**
     * Execute simple path (skip planner, quick review)
     */
    protected function executeSimplePath(PipelineContext $context): PipelineResult
    {
        Log::info('Pipeline executing simple path', [
            'request_id' => $context->requestId,
        ]);
        
        // Generate code directly
        $code = $this->codingAgent->generate($context);
        $context->setCode($code);
        
        // Quick review
        $review = $this->reviewAgent->quickReview($context);
        $context->setReview($review);
        
        // One rework attempt if needed
        if (count($review['must_fix'] ?? []) > 0) {
            $context->addReworkFeedback($review);
            $code = $this->codingAgent->generate($context);
            $context->setCode($code);
        }
        
        return new PipelineResult($context);
    }
    
    /**
     * Execute full pipeline
     */
    protected function executeFullPipeline(PipelineContext $context): PipelineResult
    {
        // Stage 2: Plan
        $this->executePlanning($context);
        
        // Coding + Review loop
        $this->executeCodingWithReview($context);
        
        // Stage 5: Tests (if required)
        if ($context->requiresTests()) {
            $this->executeTestGeneration($context);
        }
        
        // Stage 6: Final Review (for sensitive domains)
        if ($context->hasSensitiveDomain()) {
            $this->executeFinalReview($context);
        }
        
        return new PipelineResult($context);
    }
    
    /**
     * Execute planning stage
     */
    protected function executePlanning(PipelineContext $context): void
    {
        $startTime = microtime(true);
        
        $plan = $this->plannerAgent->createPlan($context);
        $context->setPlan($plan);
        
        // Gate 1: Plan Required
        $this->gateEnforcer->check('plan_required', $context);
        
        $context->recordStageMetrics('plan', [
            'duration_ms' => (microtime(true) - $startTime) * 1000,
            'steps_count' => count($plan['steps'] ?? []),
        ]);
        
        Log::info('Pipeline planning complete', [
            'request_id' => $context->requestId,
            'steps' => count($plan['steps'] ?? []),
        ]);
    }
    
    /**
     * Execute coding with review loop
     */
    protected function executeCodingWithReview(PipelineContext $context): void
    {
        $reworkCount = 0;
        
        do {
            $startTime = microtime(true);
            
            // Generate code
            $code = $this->codingAgent->generate($context);
            $context->setCode($code);
            
            // Gate 2: Patch Only
            try {
                $this->gateEnforcer->check('patch_only', $context);
            } catch (QualityGateFailedException $e) {
                if ($e->retriable && $reworkCount < $this->maxReworks) {
                    $reworkCount++;
                    continue;
                }
                throw $e;
            }
            
            $context->recordStageMetrics('code_' . $reworkCount, [
                'duration_ms' => (microtime(true) - $startTime) * 1000,
                'iteration' => $reworkCount,
            ]);
            
            // Review
            $reviewStart = microtime(true);
            $review = $this->reviewAgent->review($context);
            $context->setReview($review);
            
            $context->recordStageMetrics('review_' . $reworkCount, [
                'duration_ms' => (microtime(true) - $reviewStart) * 1000,
                'must_fix_count' => count($review['must_fix'] ?? []),
            ]);
            
            // Gate 3: Must Fix Zero
            $mustFixCount = $this->gateEnforcer->check('must_fix_zero', $context);
            
            if ($mustFixCount > 0) {
                $context->addReworkFeedback($review);
                $reworkCount++;
                
                Log::info('Pipeline rework needed', [
                    'request_id' => $context->requestId,
                    'iteration' => $reworkCount,
                    'must_fix_count' => $mustFixCount,
                ]);
            }
            
        } while ($mustFixCount > 0 && $reworkCount < $this->maxReworks);
        
        if ($mustFixCount > 0) {
            throw new ReworkLimitException(
                "Max rework attempts ({$this->maxReworks}) reached with {$mustFixCount} must_fix issues remaining"
            );
        }
    }
    
    /**
     * Execute test generation
     */
    protected function executeTestGeneration(PipelineContext $context): void
    {
        $startTime = microtime(true);
        
        $tests = $this->testAgent->generate($context);
        $context->setTests($tests);
        
        // Gate 4: Tests Required
        $this->gateEnforcer->check('tests_required', $context);
        
        $context->recordStageMetrics('test', [
            'duration_ms' => (microtime(true) - $startTime) * 1000,
        ]);
        
        Log::info('Pipeline test generation complete', [
            'request_id' => $context->requestId,
        ]);
    }
    
    /**
     * Execute final review for sensitive domains
     */
    protected function executeFinalReview(PipelineContext $context): void
    {
        $startTime = microtime(true);
        
        // Re-review with focus on safety
        $finalReview = $this->reviewAgent->review($context);
        $context->setFinalReview($finalReview);
        
        // Gate 5: Safety Gate
        $this->gateEnforcer->check('safety_gate', $context);
        
        $context->recordStageMetrics('final_review', [
            'duration_ms' => (microtime(true) - $startTime) * 1000,
        ]);
        
        Log::info('Pipeline final review complete', [
            'request_id' => $context->requestId,
        ]);
    }
}

