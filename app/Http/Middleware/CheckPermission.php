<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Sprawdź czy użytkownik jest aktywny
        if (!$user->isActive()) {
            auth()->logout();
            return redirect()->route('login')->with('error', 'Twoje konto zostało dezaktywowane.');
        }

        // Sprawdź uprawnienia
        if (!$user->hasPermission($permission)) {
            abort(403, 'Brak uprawnień do wykonania tej operacji.');
        }

        return $next($request);
    }
}
