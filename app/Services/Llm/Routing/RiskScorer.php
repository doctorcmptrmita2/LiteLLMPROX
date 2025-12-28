<?php

namespace App\Services\Llm\Routing;

/**
 * Scores risk level based on task characteristics.
 * 
 * Risk levels:
 * - low: Simple changes, 1 file, no sensitive domains
 * - medium: 2-3 files, moderate complexity
 * - high: 3+ files, concurrency/caching domains
 * - critical: Auth, billing, payment, webhooks, encryption
 */
class RiskScorer
{
    /**
     * Calculate risk score from triage data
     */
    public function score(array $triage): string
    {
        $filesEstimate = $triage['files_estimate'] ?? 1;
        $domains = $triage['domains'] ?? [];
        $taskType = $triage['task_type'] ?? 'bugfix';
        
        // Critical: Sensitive domains
        $criticalDomains = config('codexflow.budget.critical_domains', [
            'auth', 'billing', 'payment', 'webhooks', 
            'encryption', 'acl', 'permissions'
        ]);
        
        if (count(array_intersect($domains, $criticalDomains)) > 0) {
            return 'critical';
        }
        
        // High: Many files or high-risk domains
        $highRiskDomains = config('codexflow.budget.high_risk_domains', [
            'queue', 'cron', 'concurrency', 'caching',
            'rate_limit', 'retry', 'data_consistency'
        ]);
        
        if ($filesEstimate >= 3 || count(array_intersect($domains, $highRiskDomains)) > 0) {
            return 'high';
        }
        
        // Medium: 2-3 files
        if ($filesEstimate >= 2) {
            return 'medium';
        }
        
        // Consider task type
        if (in_array($taskType, ['refactor', 'feature'])) {
            return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * Check if risk requires extended thinking/reasoning
     */
    public function requiresReasoning(array $triage): bool
    {
        $risk = $triage['risk'] ?? $this->score($triage);
        $taskType = $triage['task_type'] ?? 'bugfix';
        $domains = $triage['domains'] ?? [];
        
        // High/critical always needs reasoning
        if (in_array($risk, ['high', 'critical'])) {
            return true;
        }
        
        // Research tasks need reasoning
        if ($taskType === 'research') {
            return true;
        }
        
        // Complex domains need reasoning
        $reasoningDomains = ['concurrency', 'idempotency', 'data_consistency'];
        if (count(array_intersect($domains, $reasoningDomains)) > 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get review model based on risk
     */
    public function getReviewModel(string $risk): string
    {
        return in_array($risk, ['high', 'critical']) 
            ? 'cf-premium-coder' 
            : 'cf-budget-reviewer';
    }
}

