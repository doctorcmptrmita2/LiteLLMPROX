<?php

namespace App\Services\Llm\Agents;

use App\Services\Llm\LiteLlmClient;
use App\Services\Llm\Pipeline\PipelineContext;
use App\Services\Llm\Routing\RiskScorer;

/**
 * Review Agent - Reviews generated code for issues.
 * 
 * Uses: cf-budget-reviewer (DeepSeek) or cf-premium-coder (Sonnet 4.5)
 * Output: Checklist with must_fix, should_fix, nice_to_have.
 */
class ReviewAgent
{
    protected LiteLlmClient $client;
    protected RiskScorer $riskScorer;
    
    public function __construct(
        LiteLlmClient $client,
        RiskScorer $riskScorer
    ) {
        $this->client = $client;
        $this->riskScorer = $riskScorer;
    }
    
    /**
     * Review code changes
     */
    public function review(PipelineContext $context): array
    {
        $model = $this->riskScorer->getReviewModel($context->getRisk());
        
        $systemPrompt = $this->buildSystemPrompt($context);
        $userPrompt = $this->buildUserPrompt($context);
        
        $response = $this->client->chatCompletion([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => 2500,
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
        ]);
        
        $review = json_decode($response['choices'][0]['message']['content'] ?? '{}', true);
        
        return $this->normalizeReview($review);
    }
    
    /**
     * Quick review for simple path
     */
    public function quickReview(PipelineContext $context): array
    {
        $response = $this->client->chatCompletion([
            'model' => 'cf-budget-reviewer',
            'messages' => [
                ['role' => 'system', 'content' => $this->buildQuickReviewPrompt()],
                ['role' => 'user', 'content' => "CODE:\n" . ($context->code ?? '')],
            ],
            'max_tokens' => 1000,
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
        ]);
        
        $review = json_decode($response['choices'][0]['message']['content'] ?? '{}', true);
        
        return $this->normalizeReview($review);
    }
    
    /**
     * Build system prompt for review
     */
    protected function buildSystemPrompt(PipelineContext $context): string
    {
        $triage = $context->triage ?? [];
        $domains = implode(', ', $triage['domains'] ?? []);
        
        return <<<PROMPT
You are a code reviewer. Analyze the code changes and produce a review checklist.

TASK CONTEXT:
- Type: {$triage['task_type']}
- Risk: {$triage['risk']}
- Domains: {$domains}

OUTPUT JSON FORMAT:
{
  "must_fix": ["Critical issues that MUST be fixed before merge"],
  "should_fix": ["Important issues that should be addressed"],
  "nice_to_have": ["Optional improvements"],
  "test_gaps": ["Missing test coverage"],
  "risk_notes": ["Security or operational risks"]
}

REVIEW CRITERIA:
1. Logic errors and bugs
2. Security vulnerabilities (SQL injection, XSS, etc.)
3. Race conditions and concurrency issues
4. Error handling gaps
5. Performance concerns
6. Code style and maintainability

MUST_FIX TRIGGERS:
- SQL injection risk
- Authentication bypass
- Authorization issues
- Data corruption potential
- Missing error handling for critical paths

Output ONLY valid JSON.
PROMPT;
    }
    
    /**
     * Build user prompt with code
     */
    protected function buildUserPrompt(PipelineContext $context): string
    {
        $code = $context->code ?? '';
        $userMessage = $context->getLatestUserMessage() ?? '';
        
        return <<<PROMPT
ORIGINAL REQUEST:
{$userMessage}

CODE CHANGES TO REVIEW:
{$code}

Analyze these changes and produce the review checklist.
PROMPT;
    }
    
    /**
     * Build quick review prompt
     */
    protected function buildQuickReviewPrompt(): string
    {
        return <<<PROMPT
Quick code review. Output JSON with:
{
  "must_fix": ["Critical issues only"],
  "should_fix": ["Important issues"],
  "nice_to_have": []
}

Focus only on: bugs, security issues, logic errors.
Skip style and minor improvements.
PROMPT;
    }
    
    /**
     * Normalize review output
     */
    protected function normalizeReview(array $review): array
    {
        return [
            'must_fix' => $review['must_fix'] ?? [],
            'should_fix' => $review['should_fix'] ?? [],
            'nice_to_have' => $review['nice_to_have'] ?? [],
            'test_gaps' => $review['test_gaps'] ?? [],
            'risk_notes' => $review['risk_notes'] ?? [],
        ];
    }
}

