<?php

namespace App\Services\Llm\Routing;

/**
 * Determines budget class based on task triage results.
 * 
 * Budget classes:
 * - cheap: Low risk, 1-2 files, simple tasks
 * - balanced: Medium risk, 2-5 files, standard tasks
 * - premium: High/critical risk, auth/billing/webhooks
 */
class BudgetClassifier
{
    /**
     * Classify budget based on triage data
     */
    public function classify(array $triage): string
    {
        $risk = $triage['risk'] ?? 'low';
        $filesEstimate = $triage['files_estimate'] ?? 1;
        $domains = $triage['domains'] ?? [];
        
        // Check critical domains first
        $criticalDomains = config('codexflow.budget.critical_domains', []);
        if (count(array_intersect($domains, $criticalDomains)) > 0) {
            return 'premium';
        }
        
        // Check risk level
        if (in_array($risk, ['high', 'critical'])) {
            return 'premium';
        }
        
        // Check high risk domains
        $highRiskDomains = config('codexflow.budget.high_risk_domains', []);
        if (count(array_intersect($domains, $highRiskDomains)) > 0) {
            return 'balanced';
        }
        
        // Check file count
        if ($filesEstimate >= 3) {
            return 'balanced';
        }
        
        if ($risk === 'medium' || $filesEstimate >= 2) {
            return 'balanced';
        }
        
        return 'cheap';
    }
    
    /**
     * Get model alias for budget class
     */
    public function getModelAlias(string $budgetClass): string
    {
        return match($budgetClass) {
            'premium' => 'cf-premium-coder',
            'balanced' => 'cf-balanced-coder',
            default => 'cf-cheap-coder',
        };
    }
    
    /**
     * Get per-request cost cap for budget class
     */
    public function getCostCap(string $budgetClass): float
    {
        return config("codexflow.cost_control.per_request_cap_usd.{$budgetClass}", 0.10);
    }
    
    /**
     * Downgrade budget class by one level
     */
    public function downgrade(string $budgetClass): string
    {
        return match($budgetClass) {
            'premium' => 'balanced',
            'balanced' => 'cheap',
            default => 'cheap',
        };
    }
}

