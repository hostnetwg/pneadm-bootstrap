<?php

namespace App\Http\Middleware;

use App\Services\FunnelSkipService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshFunnelSkipOptOutCookies
{
    public function __construct(
        private FunnelSkipService $funnelSkip
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($this->funnelSkip->renewalCookiesForRequest($request) as $cookie) {
            $response = $response->withCookie($cookie);
        }

        return $response;
    }
}
