<?php

namespace App\Services\Llm;

use App\Models\QuotaDaily;
use App\Models\QuotaMonthly;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

class TierSelector
{
    /**
     * Determine the appropriate tier based on request and quota.
     */
    public function selectTier(User $user, array $planConfig, ?string $requestedTier = null): array
    {
        $month = now()->format('Y-m');
        $date = now()->toDateString();

        // Get quota records
        $monthlyQuota = QuotaMonthly::getOrCreateForUser($user->id, $month);
        $dailyQuota = QuotaDaily::getOrCreateForUser($user->id, $date);

        // Requested tier (from x-quality header)
        $tier = $requestedTier ?? 'fast';

        // Check if trial with unlimited grace
        $trialGraceUnlimited = ($planConfig['is_trial'] ?? false) && ($planConfig['grace_unlimited'] ?? false);

        // Try requested tier first
        if ($tier === 'deep') {
            $result = $this->checkDeepQuota($monthlyQuota, $dailyQuota, $planConfig);
            if ($result['available']) {
                return ['tier' => 'deep', 'reason' => 'requested'];
            }
            // Fallback to fast
            $tier = 'fast';
        }

        // Try fast tier
        if ($tier === 'fast') {
            $result = $this->checkFastQuota($monthlyQuota, $dailyQuota, $planConfig);
            if ($result['available']) {
                return ['tier' => 'fast', 'reason' => 'requested'];
            }
        }

        // Try grace tier
        $graceResult = $this->checkGraceQuota($dailyQuota, $planConfig);
        
        if ($graceResult['available'] || $trialGraceUnlimited) {
            return [
                'tier' => 'grace',
                'reason' => $trialGraceUnlimited ? 'trial_grace_unlimited' : 'quota_fallback',
            ];
        }

        // No quota available
        return [
            'tier' => null,
            'reason' => 'quota_exhausted',
            'retry_after' => $this->calculateRetryAfter($dailyQuota),
        ];
    }

    /**
     * Check fast tier quota availability.
     */
    protected function checkFastQuota(QuotaMonthly $monthly, QuotaDaily $daily, array $planConfig): array
    {
        $monthlyLimits = $planConfig['monthly_quotas']['fast'] ?? $planConfig['trial_quotas']['fast'] ?? null;
        $dailyLimits = $planConfig['daily_safety']['fast'] ?? null;

        if (!$monthlyLimits) {
            return ['available' => false, 'reason' => 'no_quota_defined'];
        }

        // Check monthly limits
        if ($monthly->fast_requests >= $monthlyLimits['requests']) {
            return ['available' => false, 'reason' => 'monthly_requests_exhausted'];
        }

        $totalMonthlyTokens = $monthly->fast_input_tokens + $monthly->fast_output_tokens;
        $maxMonthlyTokens = $monthlyLimits['input_tokens'] + $monthlyLimits['output_tokens'];
        
        if ($totalMonthlyTokens >= $maxMonthlyTokens) {
            return ['available' => false, 'reason' => 'monthly_tokens_exhausted'];
        }

        // Check daily safety limits (if defined)
        if ($dailyLimits) {
            if ($daily->fast_requests >= $dailyLimits['requests']) {
                return ['available' => false, 'reason' => 'daily_requests_exhausted'];
            }

            if ($daily->fast_tokens >= $dailyLimits['tokens']) {
                return ['available' => false, 'reason' => 'daily_tokens_exhausted'];
            }
        }

        return ['available' => true];
    }

    /**
     * Check deep tier quota availability.
     */
    protected function checkDeepQuota(QuotaMonthly $monthly, QuotaDaily $daily, array $planConfig): array
    {
        $monthlyLimits = $planConfig['monthly_quotas']['deep'] ?? $planConfig['trial_quotas']['deep'] ?? null;
        $dailyLimits = $planConfig['daily_safety']['deep'] ?? null;

        if (!$monthlyLimits) {
            return ['available' => false, 'reason' => 'no_quota_defined'];
        }

        // Check monthly limits
        if ($monthly->deep_requests >= $monthlyLimits['requests']) {
            return ['available' => false, 'reason' => 'monthly_requests_exhausted'];
        }

        $totalMonthlyTokens = $monthly->deep_input_tokens + $monthly->deep_output_tokens;
        $maxMonthlyTokens = $monthlyLimits['input_tokens'] + $monthlyLimits['output_tokens'];
        
        if ($totalMonthlyTokens >= $maxMonthlyTokens) {
            return ['available' => false, 'reason' => 'monthly_tokens_exhausted'];
        }

        // Check daily safety limits (if defined)
        if ($dailyLimits) {
            if ($daily->deep_requests >= $dailyLimits['requests']) {
                return ['available' => false, 'reason' => 'daily_requests_exhausted'];
            }

            if ($daily->deep_tokens >= $dailyLimits['tokens']) {
                return ['available' => false, 'reason' => 'daily_tokens_exhausted'];
            }
        }

        return ['available' => true];
    }

    /**
     * Check grace tier quota availability.
     */
    protected function checkGraceQuota(QuotaDaily $daily, array $planConfig): array
    {
        $graceLimits = $planConfig['grace_daily'] ?? null;

        // Trial users might have unlimited grace
        if ($planConfig['grace_unlimited'] ?? false) {
            return ['available' => true, 'reason' => 'unlimited'];
        }

        if (!$graceLimits) {
            return ['available' => false, 'reason' => 'no_grace_quota'];
        }

        if ($daily->grace_requests >= $graceLimits['requests']) {
            return ['available' => false, 'reason' => 'daily_requests_exhausted'];
        }

        if ($daily->grace_tokens >= $graceLimits['tokens']) {
            return ['available' => false, 'reason' => 'daily_tokens_exhausted'];
        }

        return ['available' => true];
    }

    /**
     * Calculate retry-after based on daily reset.
     */
    protected function calculateRetryAfter(QuotaDaily $daily): int
    {
        // Calculate seconds until midnight
        $midnight = now()->endOfDay();
        return now()->diffInSeconds($midnight);
    }
}


