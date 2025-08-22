<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PubligoWebhookMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Logowanie wszystkich requestów do webhooka
        Log::info('Publigo webhook request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'body' => $request->all()
        ]);

        // Sprawdzenie czy to POST request
        if ($request->method() !== 'POST') {
            Log::warning('Invalid method for webhook', ['method' => $request->method()]);
            return response()->json(['message' => 'Method not allowed'], 405);
        }

        // Sprawdzenie Content-Type - uproszczone
        $contentType = $request->header('Content-Type');
        if (!$contentType || !str_contains($contentType, 'application/json')) {
            Log::warning('Invalid content type for webhook', [
                'content_type' => $contentType
            ]);
            // Nie blokujemy - pozwalamy przejść dalej
        }

        // Sprawdzenie tokenu webhooka (jeśli jest wymagany)
        $webhookToken = $request->header('X-Webhook-Token') ?? $request->header('Authorization');
        if ($webhookToken) {
            Log::info('Webhook token received', [
                'token' => $webhookToken,
                'token_length' => strlen($webhookToken)
            ]);
            
            // Tutaj możesz dodać weryfikację tokenu
            // $expectedToken = config('services.publigo.webhook_token');
            // if ($webhookToken !== $expectedToken) {
            //     Log::warning('Invalid webhook token', ['token' => $webhookToken]);
            //     return response()->json(['message' => 'Unauthorized'], 401);
            // }
        }

        return $next($request);
    }
}
