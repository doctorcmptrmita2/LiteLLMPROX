<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LlmRequest;
use App\Models\QuotaDaily;
use App\Models\QuotaMonthly;
use App\Models\UsageDailyAggregate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    /**
     * Get daily usage statistics.
     */
    public function daily(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date|before_or_equal:today',
            'to' => 'required|date|after_or_equal:from|before_or_equal:today',
            'project_id' => 'sometimes|integer|exists:projects,id',
        ]);

        $user = $request->user();
        $from = $request->input('from');
        $to = $request->input('to');

        // Get project IDs for user
        $projectIds = $user->projects()->pluck('id');

        // Filter by specific project if requested
        if ($request->has('project_id')) {
            $projectId = $request->input('project_id');
            
            // Verify user owns project
            if (!$projectIds->contains($projectId)) {
                return response()->json([
                    'error' => [
                        'message' => 'Project not found',
                        'type' => 'not_found_error',
                    ],
                ], 404);
            }

            $projectIds = collect([$projectId]);
        }

        // Get aggregated usage
        $usage = UsageDailyAggregate::whereIn('project_id', $projectIds)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date->toDateString(),
                'project_id' => $row->project_id,
                'fast' => [
                    'tokens' => $row->fast_tokens,
                    'requests' => $row->fast_requests,
                    'cost_usd' => (float) $row->fast_cost_usd,
                ],
                'deep' => [
                    'tokens' => $row->deep_tokens,
                    'requests' => $row->deep_requests,
                    'cost_usd' => (float) $row->deep_cost_usd,
                ],
                'grace' => [
                    'tokens' => $row->grace_tokens,
                    'requests' => $row->grace_requests,
                    'cost_usd' => (float) $row->grace_cost_usd,
                ],
                'planner' => [
                    'tokens' => $row->planner_tokens,
                    'requests' => $row->planner_requests,
                ],
                'total' => [
                    'tokens' => $row->total_tokens,
                    'requests' => $row->total_requests,
                    'cost_usd' => (float) $row->total_cost_usd,
                ],
                'cache_hits' => $row->cache_hits,
                'decomposed_requests' => $row->decomposed_requests,
            ]);

        return response()->json([
            'data' => $usage,
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    /**
     * Get monthly usage summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $user = $request->user();
        $month = $request->input('month');

        // Get subscription and plan config
        $subscription = $user->activeSubscription;
        $planConfig = $subscription?->getPlanConfig();

        // Get monthly quota usage
        $quotaMonthly = QuotaMonthly::where('user_id', $user->id)
            ->where('month', $month)
            ->first();

        // Get today's daily quota
        $quotaDaily = QuotaDaily::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        // Calculate limits from plan
        $monthlyQuotas = $planConfig['monthly_quotas'] ?? $planConfig['trial_quotas'] ?? [];
        $dailySafety = $planConfig['daily_safety'] ?? [];
        $graceDaily = $planConfig['grace_daily'] ?? [];

        // Build response
        $response = [
            'month' => $month,
            'plan' => [
                'code' => $subscription?->plan_code,
                'name' => $subscription?->getPlanName(),
                'is_trial' => $subscription?->is_trial ?? false,
            ],
            'usage' => [
                'fast' => [
                    'input_tokens' => $quotaMonthly?->fast_input_tokens ?? 0,
                    'output_tokens' => $quotaMonthly?->fast_output_tokens ?? 0,
                    'requests' => $quotaMonthly?->fast_requests ?? 0,
                ],
                'deep' => [
                    'input_tokens' => $quotaMonthly?->deep_input_tokens ?? 0,
                    'output_tokens' => $quotaMonthly?->deep_output_tokens ?? 0,
                    'requests' => $quotaMonthly?->deep_requests ?? 0,
                ],
                'planner' => [
                    'tokens' => $quotaMonthly?->planner_tokens ?? 0,
                ],
            ],
            'limits' => [
                'fast' => [
                    'input_tokens' => $monthlyQuotas['fast']['input_tokens'] ?? 0,
                    'output_tokens' => $monthlyQuotas['fast']['output_tokens'] ?? 0,
                    'requests' => $monthlyQuotas['fast']['requests'] ?? 0,
                ],
                'deep' => [
                    'input_tokens' => $monthlyQuotas['deep']['input_tokens'] ?? 0,
                    'output_tokens' => $monthlyQuotas['deep']['output_tokens'] ?? 0,
                    'requests' => $monthlyQuotas['deep']['requests'] ?? 0,
                ],
            ],
            'daily' => [
                'date' => now()->toDateString(),
                'usage' => [
                    'fast' => [
                        'tokens' => $quotaDaily?->fast_tokens ?? 0,
                        'requests' => $quotaDaily?->fast_requests ?? 0,
                    ],
                    'deep' => [
                        'tokens' => $quotaDaily?->deep_tokens ?? 0,
                        'requests' => $quotaDaily?->deep_requests ?? 0,
                    ],
                    'grace' => [
                        'tokens' => $quotaDaily?->grace_tokens ?? 0,
                        'requests' => $quotaDaily?->grace_requests ?? 0,
                    ],
                ],
                'limits' => [
                    'fast' => $dailySafety['fast'] ?? null,
                    'deep' => $dailySafety['deep'] ?? null,
                    'grace' => $graceDaily,
                ],
            ],
            'percentages' => $this->calculatePercentages($quotaMonthly, $monthlyQuotas),
        ];

        return response()->json($response);
    }

    /**
     * Calculate usage percentages.
     */
    protected function calculatePercentages(?QuotaMonthly $quota, array $limits): array
    {
        if (!$quota) {
            return ['fast' => 0, 'deep' => 0];
        }

        $fastLimit = ($limits['fast']['input_tokens'] ?? 0) + ($limits['fast']['output_tokens'] ?? 0);
        $deepLimit = ($limits['deep']['input_tokens'] ?? 0) + ($limits['deep']['output_tokens'] ?? 0);

        $fastUsed = $quota->fast_input_tokens + $quota->fast_output_tokens;
        $deepUsed = $quota->deep_input_tokens + $quota->deep_output_tokens;

        return [
            'fast' => $fastLimit > 0 ? round(($fastUsed / $fastLimit) * 100, 1) : 0,
            'deep' => $deepLimit > 0 ? round(($deepUsed / $deepLimit) * 100, 1) : 0,
        ];
    }
}

