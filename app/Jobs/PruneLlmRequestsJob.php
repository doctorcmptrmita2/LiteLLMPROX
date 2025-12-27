<?php

namespace App\Jobs;

use App\Models\LlmRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PruneLlmRequestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $retentionDays = config('codexflow.retention.llm_requests_days', 21);
        $cutoffDate = now()->subDays($retentionDays);

        Log::info("Pruning LLM requests older than {$cutoffDate->toDateString()}");

        // Delete in chunks to avoid locking
        $deletedTotal = 0;
        $chunkSize = 1000;

        do {
            $deleted = LlmRequest::where('created_at', '<', $cutoffDate)
                ->limit($chunkSize)
                ->delete();

            $deletedTotal += $deleted;

            // Small delay between chunks
            if ($deleted > 0) {
                usleep(100000); // 100ms
            }

        } while ($deleted > 0);

        Log::info("Pruned {$deletedTotal} old LLM request records.");
    }
}

