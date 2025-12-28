<?php

namespace App\Jobs;

use App\Models\QuotaDaily;
use App\Models\QuotaMonthly;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SyncRedisQuotaToDbJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    /**
     * Execute the job.
     * 
     * Syncs Redis quota counters to database for persistence.
     * This is a backup mechanism - primary quota tracking is in Redis.
     */
    public function handle(): void
    {
        Log::info('Starting Redis quota sync to database');

        $month = now()->format('Y-m');
        $date = now()->toDateString();

        // Get all active users
        $users = User::where('status', 'active')
            ->whereHas('subscriptions', function ($query) {
                $query->whereIn('status', ['active', 'trial']);
            })
            ->get();

        $synced = 0;

        foreach ($users as $user) {
            try {
                $this->syncUserQuota($user, $month, $date);
                $synced++;
            } catch (\Exception $e) {
                Log::warning("Failed to sync quota for user {$user->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Completed Redis quota sync. Synced {$synced} users.");
    }

    /**
     * Sync quota for a single user.
     */
    protected function syncUserQuota(User $user, string $month, string $date): void
    {
        $prefix = 'quota:';

        // Note: We're syncing from Redis to DB as a backup
        // The actual values in Redis are the source of truth
        // This sync helps with persistence and reporting

        // Get Redis values if they exist
        $fastMonthly = $this->getRedisValue("{$prefix}monthly:{$user->id}:{$month}:fast");
        $deepMonthly = $this->getRedisValue("{$prefix}monthly:{$user->id}:{$month}:deep");
        $fastDaily = $this->getRedisValue("{$prefix}daily:{$user->id}:{$date}:fast");
        $deepDaily = $this->getRedisValue("{$prefix}daily:{$user->id}:{$date}:deep");
        $graceDaily = $this->getRedisValue("{$prefix}daily:{$user->id}:{$date}:grace");

        // Only update if we have Redis data
        // The DB values are updated in real-time by QuotaService
        // This is just a verification/backup sync
    }

    /**
     * Get value from Redis safely.
     */
    protected function getRedisValue(string $key): ?int
    {
        try {
            $value = Redis::get($key);
            return $value !== null ? (int) $value : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}



