<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Gateway routes without /api prefix (OpenAI-compatible for Cursor)
            Route::middleware('api')
                ->group(base_path('routes/gateway.php'));
            
            // Test routes (no middleware)
            Route::group([], base_path('routes/test.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust all proxies (EasyPanel/Traefik)
        $middleware->trustProxies(at: '*');
        
        // Disable CSRF for ALL routes (temporary fix)
        $middleware->validateCsrfTokens(except: [
            '*',
        ]);
        
        // Alias middleware
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        // API middleware
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
