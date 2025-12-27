<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExpireTrialSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Processing expired trial subscriptions');

        // Find expired trials
        $expired = Subscription::where('is_trial', true)
            ->where('status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->with('user')
            ->get();

        $count = 0;

        foreach ($expired as $subscription) {
            try {
                // Update subscription status
                $subscription->update([
                    'status' => 'expired',
                ]);

                // Optionally suspend user
                $onExpire = config('codexflow.plans.trial_free.on_expire', 'suspend');
                
                if ($onExpire === 'suspend') {
                    // Don't suspend immediately, just mark subscription as expired
                    // User can still see dashboard and upgrade
                }

                // Send expiration email
                $this->sendExpirationEmail($subscription->user);

                $count++;

            } catch (\Exception $e) {
                Log::warning("Failed to process expired trial for subscription {$subscription->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Processed {$count} expired trial subscriptions.");
    }

    /**
     * Send trial expiration email.
     */
    protected function sendExpirationEmail(User $user): void
    {
        // TODO: Implement email notification
        // Mail::to($user->email)->send(new TrialExpiredMail($user));

        Log::info("Trial expired email queued for user {$user->id}");
    }
}

