<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectApiKey;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@codexflow.dev',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Create demo customer with trial
        $customer = User::create([
            'name' => 'Demo User',
            'email' => 'demo@codexflow.dev',
            'password' => Hash::make('demo123'),
            'role' => 'customer',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Create trial subscription
        Subscription::create([
            'user_id' => $customer->id,
            'plan_code' => 'trial_free',
            'starts_at' => now(),
            'ends_at' => now()->addHours(24),
            'status' => 'trial',
            'is_trial' => true,
            'trial_ends_at' => now()->addHours(24),
        ]);

        // Create demo project
        $project = Project::create([
            'user_id' => $customer->id,
            'name' => 'Demo Project',
            'slug' => 'demo-project',
            'status' => 'active',
        ]);

        // Create API key
        $keyData = ProjectApiKey::generateKey();
        $project->apiKeys()->create([
            'name' => 'Demo Key',
            'key_prefix' => $keyData['prefix'],
            'key_hash' => $keyData['hash'],
        ]);

        $this->command->info('Demo API Key: ' . $keyData['plaintext']);
        $this->command->info('Admin: admin@codexflow.dev / admin123');
        $this->command->info('Customer: demo@codexflow.dev / demo123');
    }
}
