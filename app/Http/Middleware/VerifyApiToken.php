<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-API-Token');
        $validToken = config('services.pneadm.api_token');
        
        if (!$token || $token !== $validToken) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API token'
            ], 401);
        }
        
        return $next($request);
    }
}

