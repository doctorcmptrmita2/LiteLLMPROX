<?php

namespace App\Services\Llm\Agents;

use App\Services\Llm\LiteLlmClient;
use App\Services\Llm\Pipeline\PipelineContext;
use App\Services\Llm\Routing\ModelSelector;

/**
 * Coding Agent - Generates code changes.
 * 
 * Uses: cf-cheap-coder, cf-balanced-coder, or cf-premium-coder
 * Output: Unified diff format ONLY.
 */
class CodingAgent
{
    protected LiteLlmClient $client;
    protected ModelSelector $modelSelector;
    
    public function __construct(
        LiteLlmClient $client,
        ModelSelector $modelSelector
    ) {
        $this->client = $client;
        $this->modelSelector = $modelSelector;
    }
    
    /**
     * Generate code changes
     */
    public function generate(PipelineContext $context): string
    {
        $model = $this->modelSelector->selectCodingModel($context);
        $stageConfig = $this->getStageConfigForBudget($context->getBudgetClass());
        
        $systemPrompt = $this->buildSystemPrompt($context);
        $userPrompt = $this->buildUserPrompt($context);
        
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];
        
        // Add rework feedback if any
        if ($context->getReworkCount() > 0) {
            $messages[] = [
                'role' => 'assistant',
                'content' => "Previous attempt:\n" . ($context->code ?? ''),
            ];
            $messages[] = [
                'role' => 'user',
                'content' => $this->buildReworkPrompt($context),
            ];
        }
        
        $response = $this->client->chatCompletion([
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $stageConfig['max_output_tokens'],
            'temperature' => 0.1,
        ]);
        
        return $response['choices'][0]['message']['content'] ?? '';
    }
    
    /**
     * Build system prompt
     */
    protected function buildSystemPrompt(PipelineContext $context): string
    {
        $triage = $context->triage ?? [];
        
        return <<<PROMPT
You are an expert coding agent. Generate code changes in UNIFIED DIFF FORMAT ONLY.

TASK INFO:
- Type: {$triage['task_type']}
- Risk: {$triage['risk']}
- Files: ~{$triage['files_estimate']}

OUTPUT RULES (STRICT):
1. Output ONLY unified diff patches
2. Include full file paths: --- a/path/to/file.php
3. Keep patches MINIMAL - only changed lines
4. NO full file rewrites
5. NO explanatory text outside of code comments
6. NO unrelated formatting changes

DIFF FORMAT EXAMPLE:
--- a/app/Services/Example.php
+++ b/app/Services/Example.php
@@ -10,6 +10,8 @@ class Example
     public function method()
     {
+        // Added new logic
+        \$result = \$this->newLogic();
         return \$result;
     }
 }

QUALITY REQUIREMENTS:
- Follow PSR-12 coding standards
- Use type hints and return types
- Add appropriate error handling
- Keep functions focused (Single Responsibility)
PROMPT;
    }
    
    /**
     * Build user prompt with context
     */
    protected function buildUserPrompt(PipelineContext $context): string
    {
        $userMessage = $context->getLatestUserMessage() ?? '';
        $plan = $context->plan ?? [];
        
        $prompt = "USER REQUEST:\n{$userMessage}\n\n";
        
        if (!empty($plan['steps'])) {
            $prompt .= "PLAN:\n";
            foreach ($plan['steps'] as $step) {
                $files = implode(', ', $step['files'] ?? []);
                $prompt .= "- Step {$step['id']}: {$step['goal']} (files: {$files})\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "Generate the unified diff patches for these changes.";
        
        return $prompt;
    }
    
    /**
     * Build rework prompt with review feedback
     */
    protected function buildReworkPrompt(PipelineContext $context): string
    {
        $feedback = $context->reworkFeedback->last();
        
        $prompt = "REVIEW FEEDBACK - Please fix these issues:\n\n";
        
        if (!empty($feedback['must_fix'])) {
            $prompt .= "MUST FIX:\n";
            foreach ($feedback['must_fix'] as $issue) {
                $prompt .= "- {$issue}\n";
            }
        }
        
        if (!empty($feedback['should_fix'])) {
            $prompt .= "\nSHOULD FIX:\n";
            foreach ($feedback['should_fix'] as $issue) {
                $prompt .= "- {$issue}\n";
            }
        }
        
        $prompt .= "\nGenerate updated unified diff patches addressing these issues.";
        
        return $prompt;
    }
    
    /**
     * Get stage config for budget class
     */
    protected function getStageConfigForBudget(string $budgetClass): array
    {
        $stageKey = match($budgetClass) {
            'premium' => 'code_premium',
            'balanced' => 'code_balanced',
            default => 'code_cheap',
        };
        
        return config("codexflow.pipeline.stages.{$stageKey}", [
            'max_output_tokens' => 3000,
            'timeout' => 60,
        ]);
    }
}

