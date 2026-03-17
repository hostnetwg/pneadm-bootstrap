<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
            'api.token' => \App\Http\Middleware\VerifyApiToken::class,
        ]);
        
        // Dodaj middleware globalnie do wszystkich tras web
        // $middleware->web(append: [
        //     \App\Http\Middleware\NoIndexMiddleware::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 419 Page Expired (wygasła sesja / CSRF) – dla formularza pnedu-zakupy przekieruj z powrotem z komunikatem
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.'], 419);
            }
            if ($request->path() === 'settings/pnedu-zakupy') {
                return redirect()->route('settings.pnedu-purchases.index')
                    ->with('error', 'Sesja wygasła. Odśwież stronę i zapisz ponownie.');
            }
            return redirect()->back()
                ->withInput($request->except('password', '_token'))
                ->with('error', 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.');
        });
        // Laravel czasem zamienia TokenMismatchException na HttpException(419) – obsłuż tylko dla formularza pnedu-zakupy
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.'], 419);
            }
            if ($request->path() === 'settings/pnedu-zakupy') {
                return redirect()->route('settings.pnedu-purchases.index')
                    ->with('error', 'Sesja wygasła. Odśwież stronę i zapisz ponownie.');
            }
            return null;
        });
    })->create();
