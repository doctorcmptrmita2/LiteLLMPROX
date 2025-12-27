<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user and return Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been suspended.'],
            ]);
        }

        // Create Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Register new user with trial subscription.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Check trial abuse limits
        $this->checkTrialLimits($request);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer',
            'status' => 'pending', // Requires email verification
        ]);

        // Create trial subscription
        $trialHours = config('codexflow.trial.duration_hours', 24);
        
        Subscription::create([
            'user_id' => $user->id,
            'plan_code' => config('codexflow.trial.plan_code', 'trial_free'),
            'starts_at' => now(),
            'ends_at' => now()->addHours($trialHours),
            'status' => 'trial',
            'is_trial' => true,
            'trial_ends_at' => now()->addHours($trialHours),
        ]);

        // Create default project
        Project::create([
            'user_id' => $user->id,
            'name' => 'My First Project',
            'slug' => 'my-first-project-' . substr(uniqid(), -6),
            'status' => 'active',
        ]);

        // Send verification email
        $user->sendEmailVerificationNotification();

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful. Please verify your email.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $token,
            'trial' => [
                'expires_at' => now()->addHours($trialHours)->toIso8601String(),
                'hours_remaining' => $trialHours,
            ],
        ], 201);
    }

    /**
     * Logout user (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current user info.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'email_verified' => $user->email_verified_at !== null,
            ],
            'subscription' => $subscription ? [
                'plan_code' => $subscription->plan_code,
                'plan_name' => $subscription->getPlanName(),
                'status' => $subscription->status,
                'is_trial' => $subscription->is_trial,
                'ends_at' => $subscription->ends_at->toIso8601String(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * Check trial abuse limits.
     */
    protected function checkTrialLimits(Request $request): void
    {
        $limits = config('codexflow.trial.limits', []);

        // Check email domain limit
        $emailDomain = substr(strrchr($request->email, '@'), 1);
        $domainLimit = $limits['max_trials_per_email_domain'] ?? 3;
        
        $domainCount = User::where('email', 'like', "%@{$emailDomain}")
            ->whereHas('subscriptions', fn($q) => $q->where('is_trial', true))
            ->count();

        if ($domainCount >= $domainLimit) {
            throw ValidationException::withMessages([
                'email' => ['Too many trial accounts from this email domain.'],
            ]);
        }

        // Check IP limit
        $ip = $request->ip();
        $ipLimit = $limits['max_trials_per_ip'] ?? 2;
        
        // Simple IP check via session - more robust solution would use Redis
        $ipTrials = cache()->get("trial_ip:{$ip}", 0);
        
        if ($ipTrials >= $ipLimit) {
            throw ValidationException::withMessages([
                'email' => ['Too many trial accounts from this IP address.'],
            ]);
        }

        // Increment IP counter
        cache()->put("trial_ip:{$ip}", $ipTrials + 1, now()->addDays(30));

        // Block disposable emails
        if ($limits['disposable_email_block'] ?? true) {
            $disposableDomains = ['tempmail.com', 'guerrillamail.com', '10minutemail.com'];
            if (in_array($emailDomain, $disposableDomains)) {
                throw ValidationException::withMessages([
                    'email' => ['Disposable email addresses are not allowed.'],
                ]);
            }
        }
    }
}

