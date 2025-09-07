<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'publigo.webhook' => \App\Http\Middleware\PubligoWebhookMiddleware::class,
            'noindex' => \App\Http\Middleware\NoIndexMiddleware::class,
            'check.user.status' => \App\Http\Middleware\CheckUserStatus::class,
        ]);
        
        // Dodaj middleware globalnie do wszystkich tras web
        // $middleware->web(append: [
        //     \App\Http\Middleware\NoIndexMiddleware::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
