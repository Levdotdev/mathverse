<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Keep this after Laravel's trusted-proxy normalization so deployments
        // behind an explicitly configured load balancer validate the public
        // host rather than the proxy's internal Host header.
        $middleware->append(\App\Http\Middleware\TrustedHost::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            'auth.supabase' => \App\Http\Middleware\SupabaseAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'token',
            'token_hash',
            'token_type',
        ]);
    })->create();
