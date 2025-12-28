<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     * Redis-based rate limiting per API key and per user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->attributes->get('api_key');
        $user = $request->attributes->get('user');

        if (!$apiKey || !$user) {
            // Skip rate limiting if no auth context
            return $next($request);
        }

        $perKeyLimit = config('codexflow.rate_limits.per_key_per_minute', 60);
        $perUserLimit = config('codexflow.rate_limits.per_user_per_minute', 120);

        // Check per-key rate limit
        $keyResult = $this->checkLimit(
            "rate_limit:key:{$apiKey->id}",
            $perKeyLimit,
            60
        );

        if (!$keyResult['allowed']) {
            return $this->tooManyRequests($keyResult['retry_after'], 'per_key');
        }

        // Check per-user rate limit
        $userResult = $this->checkLimit(
            "rate_limit:user:{$user->id}",
            $perUserLimit,
            60
        );

        if (!$userResult['allowed']) {
            return $this->tooManyRequests($userResult['retry_after'], 'per_user');
        }

        // Add rate limit headers to response
        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', $perKeyLimit);
        $response->headers->set('X-RateLimit-Remaining', max(0, $keyResult['remaining']));
        $response->headers->set('X-RateLimit-Reset', $keyResult['reset_at']);

        return $response;
    }

    /**
     * Check rate limit using Redis sliding window or Cache fallback.
     */
    protected function checkLimit(string $key, int $limit, int $windowSeconds): array
    {
        $now = time();
        $windowStart = $now - $windowSeconds;

        // Use Redis if available, otherwise fallback to Cache
        if (config('cache.default') === 'redis' && class_exists('Redis')) {
            try {
                $result = Redis::pipeline(function ($pipe) use ($key, $now, $windowStart, $windowSeconds) {
                    // Remove old entries
                    $pipe->zremrangebyscore($key, '-inf', $windowStart);
                    // Add current request
                    $pipe->zadd($key, $now, $now . ':' . uniqid());
                    // Count requests in window
                    $pipe->zcard($key);
                    // Set expiry
                    $pipe->expire($key, $windowSeconds + 1);
                });

                $count = $result[2] ?? 0;
            } catch (\Exception $e) {
                // Fallback to cache if Redis fails
                $count = $this->checkLimitWithCache($key, $limit, $windowSeconds);
            }
        } else {
            // Use cache-based rate limiting for testing
            $count = $this->checkLimitWithCache($key, $limit, $windowSeconds);
        }

        $remaining = $limit - $count;
        $resetAt = $now + $windowSeconds;

        return [
            'allowed' => $remaining >= 0,
            'remaining' => $remaining,
            'reset_at' => $resetAt,
            'retry_after' => $remaining < 0 ? $windowSeconds : 0,
        ];
    }

    /**
     * Fallback rate limiting using Cache (for testing).
     */
    protected function checkLimitWithCache(string $key, int $limit, int $windowSeconds): int
    {
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        // Get existing requests from cache
        $requests = Cache::get($key, []);
        
        // Filter out old requests
        $requests = array_filter($requests, fn($timestamp) => $timestamp >= $windowStart);
        
        // Add current request
        $requests[] = $now;
        
        // Store back to cache
        Cache::put($key, $requests, $windowSeconds + 1);
        
        return count($requests);
    }

    /**
     * Return 429 Too Many Requests response.
     */
    protected function tooManyRequests(int $retryAfter, string $limitType): Response
    {
        return response()->json([
            'error' => [
                'message' => 'Rate limit exceeded. Please try again later.',
                'type' => 'rate_limit_error',
                'code' => "rate_limit_{$limitType}",
                'retry_after' => $retryAfter,
            ],
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Remaining' => 0,
        ]);
    }
}



