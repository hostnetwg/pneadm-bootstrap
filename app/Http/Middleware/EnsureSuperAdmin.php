<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user || ! $user->isSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Brak uprawnień. Tylko Super Administrator może zarządzać rozliczeniami i notatką o instruktorze.',
                ], 403);
            }

            abort(403, 'Brak uprawnień. Tylko Super Administrator może zarządzać rozliczeniami i notatką o instruktorze.');
        }

        return $next($request);
    }
}
