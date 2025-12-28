<?php

namespace App\Jobs;

use App\Models\LlmRequest;
use App\Models\UsageDailyAggregate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AggregateUsageDailyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    protected ?string $date;

    public function __construct(?string $date = null)
    {
        $this->date = $date ?? now()->subDay()->toDateString();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting daily usage aggregation for {$this->date}");

        // Get all projects with activity on this date
        $projectStats = LlmRequest::where(DB::raw('DATE(created_at)'), $this->date)
            ->select('project_id')
            ->selectRaw('SUM(CASE WHEN tier = "fast" THEN total_tokens ELSE 0 END) as fast_tokens')
            ->selectRaw('SUM(CASE WHEN tier = "fast" THEN 1 ELSE 0 END) as fast_requests')
            ->selectRaw('SUM(CASE WHEN tier = "fast" THEN cost_usd ELSE 0 END) as fast_cost')
            ->selectRaw('SUM(CASE WHEN tier = "deep" THEN total_tokens ELSE 0 END) as deep_tokens')
            ->selectRaw('SUM(CASE WHEN tier = "deep" THEN 1 ELSE 0 END) as deep_requests')
            ->selectRaw('SUM(CASE WHEN tier = "deep" THEN cost_usd ELSE 0 END) as deep_cost')
            ->selectRaw('SUM(CASE WHEN tier = "grace" THEN total_tokens ELSE 0 END) as grace_tokens')
            ->selectRaw('SUM(CASE WHEN tier = "grace" THEN 1 ELSE 0 END) as grace_requests')
            ->selectRaw('SUM(CASE WHEN tier = "grace" THEN cost_usd ELSE 0 END) as grace_cost')
            ->selectRaw('SUM(CASE WHEN tier = "planner" THEN total_tokens ELSE 0 END) as planner_tokens')
            ->selectRaw('SUM(CASE WHEN tier = "planner" THEN 1 ELSE 0 END) as planner_requests')
            ->selectRaw('SUM(total_tokens) as total_tokens')
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('SUM(cost_usd) as total_cost')
            ->selectRaw('SUM(CASE WHEN is_cached = 1 THEN 1 ELSE 0 END) as cache_hits')
            ->selectRaw('SUM(CASE WHEN is_decomposed = 1 THEN 1 ELSE 0 END) as decomposed_requests')
            ->groupBy('project_id')
            ->get();

        $count = 0;

        foreach ($projectStats as $stat) {
            UsageDailyAggregate::updateOrCreate(
                [
                    'project_id' => $stat->project_id,
                    'date' => $this->date,
                ],
                [
                    'fast_tokens' => $stat->fast_tokens ?? 0,
                    'fast_requests' => $stat->fast_requests ?? 0,
                    'fast_cost_usd' => $stat->fast_cost ?? 0,
                    'deep_tokens' => $stat->deep_tokens ?? 0,
                    'deep_requests' => $stat->deep_requests ?? 0,
                    'deep_cost_usd' => $stat->deep_cost ?? 0,
                    'grace_tokens' => $stat->grace_tokens ?? 0,
                    'grace_requests' => $stat->grace_requests ?? 0,
                    'grace_cost_usd' => $stat->grace_cost ?? 0,
                    'planner_tokens' => $stat->planner_tokens ?? 0,
                    'planner_requests' => $stat->planner_requests ?? 0,
                    'total_tokens' => $stat->total_tokens ?? 0,
                    'total_requests' => $stat->total_requests ?? 0,
                    'total_cost_usd' => $stat->total_cost ?? 0,
                    'cache_hits' => $stat->cache_hits ?? 0,
                    'decomposed_requests' => $stat->decomposed_requests ?? 0,
                ]
            );

            $count++;
        }

        Log::info("Completed daily usage aggregation for {$this->date}. Processed {$count} projects.");
    }
}



