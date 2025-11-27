<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoIndexMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Dodaj nagłówki zabezpieczające przed indeksowaniem
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        
        // Dodatkowe nagłówki bezpieczeństwa
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://cdn.jsdelivr.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data:; connect-src 'self'; frame-src 'self' https://www.youtube.com https://player.vimeo.com; media-src 'self' https://www.youtube.com https://player.vimeo.com");
        
        return $response;
    }
}
