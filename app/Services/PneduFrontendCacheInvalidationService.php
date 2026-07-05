<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PneduFrontendCacheInvalidationService
{
    public function invalidateUpcomingCourses(): void
    {
        $baseUrl = rtrim((string) config('services.pnedu.internal_url'), '/');
        $token = (string) config('services.pnedu.internal_api_token');

        if ($baseUrl === '' || $token === '') {
            Log::debug('Pnedu upcoming-courses cache invalidation skipped — brak URL lub tokena.');

            return;
        }

        $url = $baseUrl.'/api/internal/cache/upcoming-courses';

        try {
            $response = Http::timeout(5)
                ->withToken($token)
                ->acceptJson()
                ->post($url);

            if (! $response->successful()) {
                Log::warning('Pnedu upcoming-courses cache invalidation failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Pnedu upcoming-courses cache invalidation error', [
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
