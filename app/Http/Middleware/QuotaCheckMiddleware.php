<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuotaCheckMiddleware
{
    /**
     * Handle an incoming request.
     * Basic quota check - detailed check happens in GatewayService.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->attributes->get('user');

        if (!$user) {
            return $next($request);
        }

        $subscription = $user->activeSubscription;
        
        if (!$subscription) {
            return $this->quotaExceeded('No active subscription');
        }

        $planConfig = $subscription->getPlanConfig();

        if (!$planConfig) {
            return $this->quotaExceeded('Invalid plan configuration');
        }

        // For trial users, check if trial-specific quotas are exhausted
        // But allow grace lane (handled in GatewayService)
        if ($subscription->isTrial() && ($planConfig['grace_unlimited'] ?? false)) {
            // Trial with unlimited grace - always allow, tier selection in gateway
            $request->attributes->set('trial_grace_unlimited', true);
        }

        // Store plan config in request for GatewayService
        $request->attributes->set('plan_config', $planConfig);

        return $next($request);
    }

    /**
     * Return quota exceeded response.
     */
    protected function quotaExceeded(string $message): Response
    {
        return response()->json([
            'error' => [
                'message' => $message,
                'type' => 'quota_error',
                'code' => 'quota_check_failed',
            ],
        ], 429);
    }
}


