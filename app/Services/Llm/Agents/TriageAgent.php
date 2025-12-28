<?php

namespace App\Services\Llm\Agents;

use App\Services\Llm\LiteLlmClient;
use App\Services\Llm\Routing\RiskScorer;
use App\Services\Llm\Routing\BudgetClassifier;

/**
 * Triage Agent - Classifies incoming requests.
 * 
 * Uses: GPT-4o-mini (cf-triage)
 * Output: JSON with task_type, risk, budget_class, etc.
 */
class TriageAgent
{
    protected LiteLlmClient $client;
    protected RiskScorer $riskScorer;
    protected BudgetClassifier $budgetClassifier;
    
    public function __construct(
        LiteLlmClient $client,
        RiskScorer $riskScorer,
        BudgetClassifier $budgetClassifier
    ) {
        $this->client = $client;
        $this->riskScorer = $riskScorer;
        $this->budgetClassifier = $budgetClassifier;
    }
    
    /**
     * Analyze user request and produce triage JSON
     */
    public function analyze(array $messages): array
    {
        $systemPrompt = $this->buildSystemPrompt();
        
        $triageMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$this->extractRelevantMessages($messages),
        ];
        
        $response = $this->client->chatCompletion([
            'model' => 'cf-triage',
            'messages' => $triageMessages,
            'max_tokens' => 800,
            'temperature' => 0.1, // Low temp for consistent classification
            'response_format' => ['type' => 'json_object'],
        ]);
        
        $triage = json_decode($response['choices'][0]['message']['content'] ?? '{}', true);
        
        // Ensure required fields exist
        $triage = $this->normalizeTriageOutput($triage);
        
        // Calculate risk and budget if not provided by LLM
        if (!isset($triage['risk'])) {
            $triage['risk'] = $this->riskScorer->score($triage);
        }
        
        if (!isset($triage['budget_class'])) {
            $triage['budget_class'] = $this->budgetClassifier->classify($triage);
        }
        
        return $triage;
    }
    
    /**
     * Build system prompt for triage
     */
    protected function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are a task classifier for a coding assistant pipeline.
Analyze the user's request and output JSON with these fields:

{
  "task_type": "bugfix|feature|refactor|review|test_only|ui_feedback|research",
  "risk": "low|medium|high|critical",
  "files_estimate": <number 1-20>,
  "domains": ["gateway", "quota", "auth", "billing", "ui", ...],
  "needs_ui": <boolean>,
  "needs_deep_review": <boolean>,
  "budget_class": "cheap|balanced|premium",
  "acceptance_criteria": ["criterion1", "criterion2"],
  "missing_info": ["what info is missing if any"]
}

RISK CLASSIFICATION:
- low: Single file, simple change, no sensitive domains
- medium: 2-3 files, moderate complexity
- high: 3+ files, concurrency/caching involved
- critical: auth, billing, payment, webhooks, encryption, permissions

BUDGET CLASSIFICATION:
- cheap: risk=low, files<=2, no sensitive domains
- balanced: risk=medium, files=2-5, standard complexity
- premium: risk=high/critical, sensitive domains

DOMAINS (detect from context):
- auth, billing, payment, webhooks, encryption, acl, permissions
- gateway, quota, rate_limit, caching, queue, cron
- ui, api, database, migration, testing

Output ONLY valid JSON, no explanation.
PROMPT;
    }
    
    /**
     * Extract relevant messages for triage
     */
    protected function extractRelevantMessages(array $messages): array
    {
        // Take last 3 messages to limit context
        $recent = array_slice($messages, -3);
        
        return array_map(function($msg) {
            return [
                'role' => $msg['role'],
                'content' => $this->truncateContent($msg['content'] ?? '', 3000),
            ];
        }, $recent);
    }
    
    /**
     * Truncate content to max length
     */
    protected function truncateContent(string $content, int $maxLength): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }
        
        return substr($content, 0, $maxLength) . '...[truncated]';
    }
    
    /**
     * Normalize triage output to ensure all fields exist
     */
    protected function normalizeTriageOutput(array $triage): array
    {
        return array_merge([
            'task_type' => 'bugfix',
            'risk' => 'low',
            'files_estimate' => 1,
            'domains' => [],
            'needs_ui' => false,
            'needs_deep_review' => false,
            'budget_class' => 'cheap',
            'acceptance_criteria' => [],
            'missing_info' => [],
        ], $triage);
    }
}

