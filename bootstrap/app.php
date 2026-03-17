<?php

use App\Http\Middleware\ContentSecurityPolicy;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Security headers on every response
        $middleware->append(ContentSecurityPolicy::class);

        // Redirect unauthenticated users to /login
        $middleware->redirectGuestsTo('/login');

        // Redirect authenticated users away from guest-only pages to /users
        $middleware->redirectUsersTo('/users');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
