<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sprawdź czy użytkownik jest zalogowany
        if (Auth::check()) {
            $user = Auth::user();
            
            // Sprawdź czy użytkownik jest nieaktywny
            if (!$user->is_active) {
                // Wyloguj użytkownika
                Auth::logout();
                
                // Unieważnij sesję
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                // Przekieruj na stronę logowania z komunikatem
                return redirect()->route('login')
                    ->with('error', 'Twoje konto zostało dezaktywowane. Skontaktuj się z administratorem.');
            }
        }

        return $next($request);
    }
}
