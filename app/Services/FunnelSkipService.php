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

    public function tokenParam(): string
    {
        return (string) config('marketing.funnel_skip_token_param', 'token');
    }

    public function pneduToggleUrl(bool $enable, ?string $admReturnUrl = null): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $query = [
            $this->queryParam() => $enable ? '1' : '0',
            $this->tokenParam() => (string) config('marketing.funnel_skip_token'),
        ];

        if (filled($admReturnUrl)) {
            $query['adm_return'] = $admReturnUrl;
        }

        $base = rtrim((string) config('marketing.pnedu_public_url', 'https://pnedu.pl'), '/');

        return $base.'/?'.http_build_query($query);
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
}
