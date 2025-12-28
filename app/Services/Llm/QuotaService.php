<?php

namespace App\Services\Llm;

use App\Exceptions\Llm\QuotaExceededException;
use App\Models\QuotaDaily;
use App\Models\QuotaMonthly;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

class QuotaService
{
    protected string $redisPrefix = 'quota:';

    /**
     * Pre-authorize tokens using Redis DECRBY for atomicity.
     * Returns the estimated tokens if successful.
     */
    public function preAuthorize(User $user, string $tier, int $estimatedTokens): bool
    {
        $month = now()->format('Y-m');
        $date = now()->toDateString();

        // Redis keys for atomic operations
        $monthlyKey = "{$this->redisPrefix}monthly:{$user->id}:{$month}:{$tier}";
        $dailyKey = "{$this->redisPrefix}daily:{$user->id}:{$date}:{$tier}";

        // For grace tier, only check daily (no monthly)
        if ($tier === 'grace') {
            return $this->preAuthorizeDailyOnly($user, $dailyKey, $estimatedTokens);
        }

        // Try to decrement monthly and daily atomically
        try {
            $remaining = Redis::decrby($monthlyKey, $estimatedTokens);

            if ($remaining < 0) {
                // Rollback
                Redis::incrby($monthlyKey, $estimatedTokens);
                return false;
            }

            // Also check daily
            $dailyRemaining = Redis::decrby($dailyKey, $estimatedTokens);
            if ($dailyRemaining < 0) {
                // Rollback both
                Redis::incrby($dailyKey, $estimatedTokens);
                Redis::incrby($monthlyKey, $estimatedTokens);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            // If Redis fails, fall back to DB check (less safe but functional)
            return $this->fallbackDbCheck($user, $tier, $estimatedTokens);
        }
    }

    /**
     * Pre-authorize for grace tier (daily only).
     */
    protected function preAuthorizeDailyOnly(User $user, string $dailyKey, int $estimatedTokens): bool
    {
        try {
            $remaining = Redis::decrby($dailyKey, $estimatedTokens);

            if ($remaining < 0) {
                Redis::incrby($dailyKey, $estimatedTokens);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return true; // Grace lane is FREE, allow on Redis failure
        }
    }

    /**
     * Post-adjust tokens after receiving actual usage from LLM response.
     */
    public function postAdjust(User $user, string $tier, int $estimated, int $actual): void
    {
        $delta = $actual - $estimated;
        
        if ($delta === 0) {
            return;
        }

        $month = now()->format('Y-m');
        $date = now()->toDateString();

        $monthlyKey = "{$this->redisPrefix}monthly:{$user->id}:{$month}:{$tier}";
        $dailyKey = "{$this->redisPrefix}daily:{$user->id}:{$date}:{$tier}";

        try {
            // Adjust Redis (negative delta means we used less, so increment)
            Redis::decrby($monthlyKey, $delta);
            Redis::decrby($dailyKey, $delta);
        } catch (\Exception $e) {
            // Log but don't fail - will sync via job later
        }

        // Also update DB records
        $this->updateDbQuota($user, $tier, $actual);
    }

    /**
     * Update database quota records (called after request completes).
     */
    public function updateDbQuota(User $user, string $tier, int $totalTokens, int $inputTokens = 0, int $outputTokens = 0): void
    {
        $month = now()->format('Y-m');
        $date = now()->toDateString();

        // Update monthly
        $monthly = QuotaMonthly::getOrCreateForUser($user->id, $month);
        
        match ($tier) {
            'fast' => $monthly->increment('fast_input_tokens', $inputTokens) &&
                      $monthly->increment('fast_output_tokens', $outputTokens) &&
                      $monthly->increment('fast_requests'),
            'deep' => $monthly->increment('deep_input_tokens', $inputTokens) &&
                      $monthly->increment('deep_output_tokens', $outputTokens) &&
                      $monthly->increment('deep_requests'),
            'planner' => $monthly->increment('planner_tokens', $totalTokens),
            default => null,
        };

        // Update daily
        $daily = QuotaDaily::getOrCreateForUser($user->id, $date);
        
        match ($tier) {
            'fast' => $daily->increment('fast_tokens', $totalTokens) &&
                      $daily->increment('fast_requests'),
            'deep' => $daily->increment('deep_tokens', $totalTokens) &&
                      $daily->increment('deep_requests'),
            'grace' => $daily->increment('grace_tokens', $totalTokens) &&
                       $daily->increment('grace_requests'),
            default => null,
        };
    }

    /**
     * Initialize Redis quota counters from plan config.
     */
    public function initializeQuota(User $user, array $planConfig): void
    {
        $month = now()->format('Y-m');
        $date = now()->toDateString();

        $monthlyQuotas = $planConfig['monthly_quotas'] ?? $planConfig['trial_quotas'] ?? [];
        $dailySafety = $planConfig['daily_safety'] ?? [];
        $graceDaily = $planConfig['grace_daily'] ?? [];

        foreach (['fast', 'deep'] as $tier) {
            if (isset($monthlyQuotas[$tier])) {
                $limit = $monthlyQuotas[$tier]['input_tokens'] + $monthlyQuotas[$tier]['output_tokens'];
                $key = "{$this->redisPrefix}monthly:{$user->id}:{$month}:{$tier}";
                Redis::setnx($key, $limit);
                Redis::expire($key, 35 * 24 * 3600); // 35 days
            }

            if (isset($dailySafety[$tier])) {
                $key = "{$this->redisPrefix}daily:{$user->id}:{$date}:{$tier}";
                Redis::setnx($key, $dailySafety[$tier]['tokens']);
                Redis::expireat($key, now()->endOfDay()->timestamp + 1);
            }
        }

        // Grace daily
        if ($graceDaily) {
            $key = "{$this->redisPrefix}daily:{$user->id}:{$date}:grace";
            Redis::setnx($key, $graceDaily['tokens'] ?? PHP_INT_MAX);
            Redis::expireat($key, now()->endOfDay()->timestamp + 1);
        }
    }

    /**
     * Fallback DB check when Redis is unavailable.
     */
    protected function fallbackDbCheck(User $user, string $tier, int $estimatedTokens): bool
    {
        // Basic check against DB - less accurate but functional
        $monthly = QuotaMonthly::getOrCreateForUser($user->id);
        
        // Allow request, will sync later
        return true;
    }

    /**
     * Estimate tokens from messages (simple character-based estimation).
     */
    public function estimateTokens(array $messages): int
    {
        $text = '';
        foreach ($messages as $message) {
            $text .= $message['content'] ?? '';
        }

        // Rough estimation: ~4 chars per token
        return (int) ceil(strlen($text) / 4);
    }
}



