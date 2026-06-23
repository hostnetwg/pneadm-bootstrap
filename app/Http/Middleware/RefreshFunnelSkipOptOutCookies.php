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

        if ($this->shouldSkipRenewal($request, $response)) {
            return $response;
        }

        foreach ($this->funnelSkip->renewalCookiesForRequest($request) as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    private function shouldSkipRenewal(Request $request, Response $response): bool
    {
        if ($request->is('settings/analityka/lejek-opt-out/*')) {
            return true;
        }

        $trackedNames = [
            $this->funnelSkip->cookieName(),
            $this->funnelSkip->untilCookieName(),
            $this->funnelSkip->analyticsCookieName(),
        ];

        foreach ($response->headers->getCookies() as $cookie) {
            if (in_array($cookie->getName(), $trackedNames, true)) {
                return true;
            }
        }

        return false;
    }
}
