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

class SendTrialReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    protected int $hoursRemaining;

    public function __construct(int $hoursRemaining)
    {
        $this->hoursRemaining = $hoursRemaining;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Sending trial reminders for users with {$this->hoursRemaining} hours remaining");

        // Calculate time window
        $minTime = now()->addHours($this->hoursRemaining - 1);
        $maxTime = now()->addHours($this->hoursRemaining);

        // Find trials expiring in this window
        $expiring = Subscription::where('is_trial', true)
            ->where('status', 'trial')
            ->whereBetween('trial_ends_at', [$minTime, $maxTime])
            ->with('user')
            ->get();

        $count = 0;

        foreach ($expiring as $subscription) {
            try {
                $this->sendReminder($subscription->user, $this->hoursRemaining);
                $count++;
            } catch (\Exception $e) {
                Log::warning("Failed to send trial reminder for user {$subscription->user_id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Sent {$count} trial reminder emails for {$this->hoursRemaining}h remaining.");
    }

    /**
     * Send reminder email to user.
     */
    protected function sendReminder(User $user, int $hoursRemaining): void
    {
        // TODO: Implement email notification
        // Mail::to($user->email)->send(new TrialReminderMail($user, $hoursRemaining));

        Log::info("Trial reminder email queued for user {$user->id} ({$hoursRemaining}h remaining)");
    }
}



