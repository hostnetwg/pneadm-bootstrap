<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;

class AnalyticsContextService
{
    public function fromRequest(Request $request): array
    {
        return [
            'route_name' => optional($request->route())->getName(),
            'path' => '/'.ltrim($request->path(), '/'),
            'referrer_domain' => $this->referrerDomain($request->headers->get('referer')),
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'utm_content' => $request->query('utm_content'),
            'utm_term' => $request->query('utm_term'),
            'campaign_code' => $request->query('campaign_code'),
        ];
    }

    private function referrerDomain(?string $referrer): ?string
    {
        if (! $referrer) {
            return null;
        }

        return parse_url($referrer, PHP_URL_HOST) ?: null;
    }
}
