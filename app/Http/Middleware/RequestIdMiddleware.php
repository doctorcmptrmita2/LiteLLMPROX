<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    /**
     * Handle an incoming request.
     * Ensures every request has a unique request ID for tracing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get request ID from header or generate new one
        $requestId = $request->header('X-Request-Id') ?? Str::uuid()->toString();

        // Store it in the request for later use
        $request->attributes->set('request_id', $requestId);

        // Process the request
        $response = $next($request);

        // Add request ID to response headers
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}



