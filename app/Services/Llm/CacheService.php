<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    protected string $version;
    protected int $ttl;

    public function __construct()
    {
        $this->version = config('codexflow.cache.version', 'v1');
        $this->ttl = config('codexflow.cache.ttl', 3600);
    }

    /**
     * Check if request should use cache.
     */
    public function isCacheable(array $payload): bool
    {
        if (!config('codexflow.cache.enabled', true)) {
            return false;
        }

        // Only cache deterministic requests
        $temperature = $payload['temperature'] ?? 1.0;
        $stream = $payload['stream'] ?? false;

        return $temperature === 0 && $stream === false;
    }

    /**
     * Generate cache key from request payload.
     */
    public function generateKey(array $payload, string $tier): string
    {
        // Normalize payload for consistent hashing
        $normalized = $this->normalizePayload($payload);
        $normalized['_tier'] = $tier;
        $normalized['_version'] = $this->version;

        $hash = hash('sha256', json_encode($normalized));

        return "llm_cache:{$hash}";
    }

    /**
     * Get cached response if exists.
     */
    public function get(string $key): ?array
    {
        return Cache::get($key);
    }

    /**
     * Store response in cache.
     */
    public function put(string $key, array $response): void
    {
        // Don't cache error responses
        if (isset($response['error'])) {
            return;
        }

        Cache::put($key, $response, $this->ttl);
    }

    /**
     * Normalize payload for consistent hashing.
     */
    protected function normalizePayload(array $payload): array
    {
        // Remove non-deterministic fields
        unset($payload['stream']);
        unset($payload['user']);
        
        // Sort messages for consistency
        if (isset($payload['messages'])) {
            // Keep order but normalize structure
            $payload['messages'] = array_map(function ($msg) {
                return [
                    'role' => $msg['role'] ?? 'user',
                    'content' => $msg['content'] ?? '',
                ];
            }, $payload['messages']);
        }

        // Sort keys for consistent hash
        ksort($payload);

        return $payload;
    }

    /**
     * Invalidate cache for a specific pattern.
     */
    public function forget(string $pattern): void
    {
        // Redis pattern delete
        try {
            $keys = Cache::getStore()->getRedis()->keys("*{$pattern}*");
            foreach ($keys as $key) {
                Cache::forget(str_replace(config('cache.prefix') . ':', '', $key));
            }
        } catch (\Exception $e) {
            // Ignore cache clear errors
        }
    }
}



