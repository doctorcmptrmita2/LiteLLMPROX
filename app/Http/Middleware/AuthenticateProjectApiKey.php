<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Models\ProjectApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateProjectApiKey
{
    /**
     * Handle an incoming request.
     * Validates the API key and attaches project/user to request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized('Missing or invalid Authorization header');
        }

        $apiKey = substr($authHeader, 7); // Remove 'Bearer ' prefix

        // Validate key format
        $prefix = config('codexflow.api_keys.prefix', 'cf_');
        if (!str_starts_with($apiKey, $prefix)) {
            return $this->unauthorized('Invalid API key format');
        }

        // Extract prefix for fast lookup (first 12 chars)
        $keyPrefix = substr($apiKey, 0, 12);

        // Find potential matching keys by prefix
        $potentialKeys = ProjectApiKey::where('key_prefix', $keyPrefix)
            ->whereNull('revoked_at')
            ->with(['project.user'])
            ->get();

        if ($potentialKeys->isEmpty()) {
            return $this->unauthorized('Invalid API key');
        }

        // Verify the full key hash (timing-safe)
        $validKey = null;
        foreach ($potentialKeys as $key) {
            if ($key->verifyKey($apiKey)) {
                $validKey = $key;
                break;
            }
        }

        if (!$validKey) {
            return $this->unauthorized('Invalid API key');
        }

        // Check if project is active
        if (!$validKey->project || !$validKey->project->isActive()) {
            return $this->forbidden('Project is not active');
        }

        // Mark key as used (async would be better for performance)
        $validKey->markUsed();

        // Attach to request
        $request->attributes->set('api_key', $validKey);
        $request->attributes->set('project', $validKey->project);
        $request->attributes->set('user', $validKey->project->user);

        return $next($request);
    }

    /**
     * Return 401 Unauthorized response.
     */
    protected function unauthorized(string $message): Response
    {
        return response()->json([
            'error' => [
                'message' => $message,
                'type' => 'authentication_error',
                'code' => 'invalid_api_key',
            ],
        ], 401);
    }

    /**
     * Return 403 Forbidden response.
     */
    protected function forbidden(string $message): Response
    {
        return response()->json([
            'error' => [
                'message' => $message,
                'type' => 'authorization_error',
                'code' => 'project_inactive',
            ],
        ], 403);
    }
}



