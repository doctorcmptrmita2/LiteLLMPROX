<?php

namespace App\Services\Llm\Agents;

use App\Services\Llm\LiteLlmClient;
use App\Services\Llm\Pipeline\PipelineContext;

/**
 * Planner Agent - Creates step-by-step execution plan.
 * 
 * Uses: GPT-4o-mini (cf-planner)
 * Output: Step plan with context requirements per step.
 */
class PlannerAgent
{
    protected LiteLlmClient $client;
    
    public function __construct(LiteLlmClient $client)
    {
        $this->client = $client;
    }
    
    /**
     * Create execution plan based on triage
     */
    public function createPlan(PipelineContext $context): array
    {
        $systemPrompt = $this->buildSystemPrompt($context);
        
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $this->buildUserPrompt($context)],
        ];
        
        $response = $this->client->chatCompletion([
            'model' => 'cf-planner',
            'messages' => $messages,
            'max_tokens' => 2000,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ]);
        
        $plan = json_decode($response['choices'][0]['message']['content'] ?? '{}', true);
        
        return $this->normalizePlan($plan);
    }
    
    /**
     * Build system prompt for planning
     */
    protected function buildSystemPrompt(PipelineContext $context): string
    {
        $triage = $context->triage ?? [];
        $budgetClass = $context->getBudgetClass();
        
        return <<<PROMPT
You are a coding task planner. Create a step-by-step plan for implementing the user's request.

TASK CONTEXT:
- Type: {$triage['task_type']}
- Risk: {$triage['risk']}
- Budget: {$budgetClass}
- Estimated files: {$triage['files_estimate']}
- Domains: {$this->formatDomains($triage['domains'] ?? [])}

OUTPUT JSON FORMAT:
{
  "summary": ["Brief summary line 1", "Brief summary line 2"],
  "steps": [
    {
      "id": 1,
      "goal": "What this step accomplishes",
      "files": ["path/to/file1.php", "path/to/file2.php"],
      "tier": "cheap|balanced|premium",
      "max_output_tokens": 700,
      "depends_on": []
    }
  ],
  "execution_order": "sequential|parallel"
}

RULES:
- 3-12 steps maximum
- Max 5 files per step
- Assign tier based on complexity:
  - cheap: Simple changes, 1-2 files
  - balanced: Medium complexity, 2-5 files
  - premium: Complex logic, auth/billing domains
- Keep max_output_tokens conservative (500-1200)
- Set execution_order to "parallel" only if steps are truly independent

Output ONLY valid JSON.
PROMPT;
    }
    
    /**
     * Build user prompt with request details
     */
    protected function buildUserPrompt(PipelineContext $context): string
    {
        $userMessage = $context->getLatestUserMessage() ?? 'No message';
        $triage = json_encode($context->triage, JSON_PRETTY_PRINT);
        
        return <<<PROMPT
USER REQUEST:
{$userMessage}

TRIAGE RESULT:
{$triage}

Create an execution plan for this task.
PROMPT;
    }
    
    /**
     * Format domains for prompt
     */
    protected function formatDomains(array $domains): string
    {
        return count($domains) > 0 ? implode(', ', $domains) : 'general';
    }
    
    /**
     * Normalize plan output
     */
    protected function normalizePlan(array $plan): array
    {
        $plan['summary'] = $plan['summary'] ?? ['Task plan'];
        $plan['steps'] = $plan['steps'] ?? [];
        $plan['execution_order'] = $plan['execution_order'] ?? 'sequential';
        
        // Ensure each step has required fields
        foreach ($plan['steps'] as $i => &$step) {
            $step['id'] = $step['id'] ?? ($i + 1);
            $step['goal'] = $step['goal'] ?? 'Execute step';
            $step['files'] = $step['files'] ?? [];
            $step['tier'] = $step['tier'] ?? 'cheap';
            $step['max_output_tokens'] = $step['max_output_tokens'] ?? 700;
            $step['depends_on'] = $step['depends_on'] ?? [];
        }
        
        return $plan;
    }
}

