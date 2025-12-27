<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     * Verifies user is active and has valid subscription.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->attributes->get('user');

        if (!$user) {
            return $this->error('User not found', 'user_not_found', 401);
        }

        // Check user status
        if ($user->isSuspended()) {
            return $this->error(
                'Your account has been suspended. Please contact support.',
                'account_suspended',
                403
            );
        }

        if ($user->status === 'pending') {
            return $this->error(
                'Please verify your email address to continue.',
                'email_not_verified',
                403
            );
        }

        // Check subscription
        if (!$user->hasActiveSubscription()) {
            return $this->error(
                'No active subscription found. Please subscribe to continue.',
                'no_active_subscription',
                403
            );
        }

        // Check if trial expired
        $subscription = $user->activeSubscription;
        if ($subscription && $subscription->isTrial() && $subscription->trialExpired()) {
            return $this->error(
                'Your trial has expired. Please upgrade to continue.',
                'trial_expired',
                403
            );
        }

        return $next($request);
    }

    /**
     * Return error response.
     */
    protected function error(string $message, string $code, int $status): Response
    {
        return response()->json([
            'error' => [
                'message' => $message,
                'type' => 'authorization_error',
                'code' => $code,
            ],
        ], $status);
    }
}

