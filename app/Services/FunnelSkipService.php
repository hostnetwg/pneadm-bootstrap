<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Cookie;

class FunnelSkipService
{
    public function cookieDomain(): ?string
    {
        $domain = config('marketing.funnel_skip_cookie_domain');

        return is_string($domain) && trim($domain) !== '' ? trim($domain) : null;
    }

    public function isConfigured(): bool
    {
        $token = config('marketing.funnel_skip_token');

        return is_string($token) && $token !== '';
    }

    public function cookieName(): string
    {
        return (string) config('marketing.funnel_skip_cookie', 'pne_skip_funnel');
    }

    public function untilCookieName(): string
    {
        return (string) config('marketing.funnel_skip_until_cookie', 'pne_skip_funnel_until');
    }

    public function queryParam(): string
    {
        return (string) config('marketing.funnel_skip_query_param', 'pne_skip_funnel');
    }

    public function analyticsQueryParam(): string
    {
        return (string) config('marketing.funnel_skip_analytics_query_param', 'pne_skip_analytics');
    }

    public function tokenParam(): string
    {
        return (string) config('marketing.funnel_skip_token_param', 'token');
    }

    public function analyticsCookieName(): string
    {
        return (string) config('marketing.funnel_skip_analytics_cookie', 'pne_skip_analytics');
    }

    public function pneduFunnelToggleUrl(bool $enableOptOut, ?string $admReturnUrl = null): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $query = [
            $this->queryParam() => $enableOptOut ? '1' : '0',
            $this->tokenParam() => (string) config('marketing.funnel_skip_token'),
            $this->analyticsQueryParam() => '0',
        ];

        if (filled($admReturnUrl)) {
            $query['adm_return'] = $admReturnUrl;
        }

        $base = rtrim((string) config('marketing.pnedu_public_url', 'https://pnedu.pl'), '/');

        return $base.'/?'.http_build_query($query);
    }

    public function pneduAnalyticsToggleUrl(bool $enableOptOut, ?string $admReturnUrl = null): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $query = [
            $this->analyticsQueryParam() => $enableOptOut ? '1' : '0',
            $this->tokenParam() => (string) config('marketing.funnel_skip_token'),
        ];

        if (filled($admReturnUrl)) {
            $query['adm_return'] = $admReturnUrl;
        }

        $base = rtrim((string) config('marketing.pnedu_public_url', 'https://pnedu.pl'), '/');

        return $base.'/?'.http_build_query($query);
    }

    /** @deprecated Użyj pneduFunnelToggleUrl() lub pneduAnalyticsToggleUrl() */
    public function pneduToggleUrl(bool $enable, ?string $admReturnUrl = null, bool $disableAnalytics = true): ?string
    {
        if ($disableAnalytics && $enable) {
            return $this->pneduFunnelToggleUrl(true, $admReturnUrl);
        }

        return $this->pneduFunnelToggleUrl($enable, $admReturnUrl);
    }

    public function makeOptOutCookie(): Cookie
    {
        $days = max(1, (int) config('marketing.funnel_skip_cookie_days', 365));
        $minutes = $days * 24 * 60;

        return cookie(
            $this->cookieName(),
            '1',
            $minutes,
            '/',
            $this->cookieDomain(),
            app()->environment('production'),
            true,
            false,
            'Lax'
        );
    }

    public function makeOptOutUntilCookie(): Cookie
    {
        $days = max(1, (int) config('marketing.funnel_skip_cookie_days', 365));
        $minutes = $days * 24 * 60;
        $until = Carbon::now()->addDays($days)->toIso8601String();

        return cookie(
            $this->untilCookieName(),
            $until,
            $minutes,
            '/',
            $this->cookieDomain(),
            app()->environment('production'),
            false,
            false,
            'Lax'
        );
    }

    public function makeAnalyticsOptOutCookie(): Cookie
    {
        $days = max(1, (int) config('marketing.funnel_skip_cookie_days', 365));
        $minutes = $days * 24 * 60;

        return cookie(
            $this->analyticsCookieName(),
            '1',
            $minutes,
            '/',
            $this->cookieDomain(),
            app()->environment('production'),
            true,
            false,
            'Lax'
        );
    }

    public function forgetOptOutCookie(): Cookie
    {
        return cookie(
            $this->cookieName(),
            '',
            -1,
            '/',
            $this->cookieDomain(),
            app()->environment('production'),
            true,
            false,
            'Lax'
        );
    }

    public function forgetOptOutUntilCookie(): Cookie
    {
        return cookie(
            $this->untilCookieName(),
            '',
            -1,
            '/',
            $this->cookieDomain(),
            app()->environment('production'),
            false,
            false,
            'Lax'
        );
    }

    public function forgetAnalyticsOptOutCookie(): Cookie
    {
        return cookie(
            $this->analyticsCookieName(),
            '',
            -1,
            '/',
            $this->cookieDomain(),
            app()->environment('production'),
            true,
            false,
            'Lax'
        );
    }

    /**
     * Odnawia ważność cookie opt-out przy każdej wizycie — wyłączenie trwa do ręcznego ON.
     *
     * @return list<Cookie>
     */
    public function renewalCookiesForRequest(\Illuminate\Http\Request $request): array
    {
        $cookies = [];

        if ($request->cookie($this->cookieName()) === '1') {
            $cookies[] = $this->makeOptOutCookie();
            $cookies[] = $this->makeOptOutUntilCookie();
        }

        if ($request->cookie($this->analyticsCookieName()) === '1') {
            $cookies[] = $this->makeAnalyticsOptOutCookie();
        }

        return $cookies;
    }
}
