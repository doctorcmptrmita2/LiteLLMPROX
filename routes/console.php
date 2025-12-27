<?php

use App\Jobs\AggregateUsageDailyJob;
use App\Jobs\ExpireTrialSubscriptionsJob;
use App\Jobs\PruneLlmRequestsJob;
use App\Jobs\SendTrialReminderJob;
use App\Jobs\SyncRedisQuotaToDbJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Commands
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Aggregate daily usage - runs at 2 AM
Schedule::job(new AggregateUsageDailyJob())->dailyAt('02:00');

// Prune old LLM requests - runs at 3 AM
Schedule::job(new PruneLlmRequestsJob())->dailyAt('03:00');

// Sync Redis quota to DB - runs every hour
Schedule::job(new SyncRedisQuotaToDbJob())->hourly();

// Expire trial subscriptions - runs every 15 minutes
Schedule::job(new ExpireTrialSubscriptionsJob())->everyFifteenMinutes();

// Trial reminder emails at specific hours
$reminderHours = config('codexflow.trial.conversion.reminder_hours', [12, 20, 23]);
foreach ($reminderHours as $hoursRemaining) {
    // Calculate when to run based on hours remaining
    // For 24-hour trials: 12h = 12h into trial, 20h = 20h into trial, 23h = 23h into trial
    Schedule::job(new SendTrialReminderJob($hoursRemaining))->hourly();
}

/*
|--------------------------------------------------------------------------
| Custom Commands
|--------------------------------------------------------------------------
*/

Artisan::command('codexflow:aggregate {--date=}', function () {
    $date = $this->option('date') ?? now()->subDay()->toDateString();
    $this->info("Running aggregation for {$date}...");
    
    AggregateUsageDailyJob::dispatchSync($date);
    
    $this->info('Aggregation complete!');
})->purpose('Manually run usage aggregation for a specific date');

Artisan::command('codexflow:prune', function () {
    $this->info('Pruning old LLM requests...');
    
    PruneLlmRequestsJob::dispatchSync();
    
    $this->info('Pruning complete!');
})->purpose('Manually prune old LLM request records');

Artisan::command('codexflow:create-admin {email} {password?}', function (string $email, ?string $password = null) {
    $password = $password ?? \Illuminate\Support\Str::random(16);
    
    $user = \App\Models\User::updateOrCreate(
        ['email' => $email],
        [
            'name' => 'Admin',
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]
    );

    $this->info("Admin user created/updated: {$email}");
    $this->info("Password: {$password}");
})->purpose('Create or update an admin user');
