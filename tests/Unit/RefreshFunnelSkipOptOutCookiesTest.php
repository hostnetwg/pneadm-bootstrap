<?php

namespace Tests\Unit;

use App\Http\Middleware\RefreshFunnelSkipOptOutCookies;
use App\Services\FunnelSkipService;
use Illuminate\Http\Request;
use Tests\TestCase;

class RefreshFunnelSkipOptOutCookiesTest extends TestCase
{
    public function test_toggle_route_skips_renewal_even_when_request_has_opt_out_cookie(): void
    {
        $request = Request::create('/settings/analityka/lejek-opt-out/analytics/disable', 'GET');
        $request->cookies->set('pne_skip_analytics', '1');

        $middleware = app(RefreshFunnelSkipOptOutCookies::class);
        $response = $middleware->handle($request, function () {
            return redirect('/settings/analityka?analytics=on')
                ->withCookie(app(FunnelSkipService::class)->forgetAnalyticsOptOutCookie());
        });

        $names = collect($response->headers->getCookies())->map->getName()->all();

        $this->assertContains('pne_skip_analytics', $names);
        $this->assertNotContains('pne_skip_funnel', $names);
    }
}
