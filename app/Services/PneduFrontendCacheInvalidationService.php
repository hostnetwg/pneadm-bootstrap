<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PneduFrontendCacheInvalidationService
{
    public function invalidateUpcomingCourses(): void
    {
        $this->postInternalCacheEndpoint(
            '/api/internal/cache/upcoming-courses',
            'upcoming-courses',
        );
    }

    public function invalidateSurveySettings(): void
    {
        $this->postInternalCacheEndpoint(
            '/api/internal/cache/survey-settings',
            'survey-settings',
        );
    }

    private function postInternalCacheEndpoint(string $path, string $label): void
    {
        $baseUrl = rtrim((string) config('services.pnedu.internal_url'), '/');
        $token = (string) config('services.pnedu.internal_api_token');

        if ($baseUrl === '' || $token === '') {
            Log::debug("Pnedu {$label} cache invalidation skipped — brak URL lub tokena.");

            return;
        }

        $url = $baseUrl.$path;

        try {
            $response = Http::timeout(5)
                ->withToken($token)
                ->acceptJson()
                ->post($url);

            if (! $response->successful()) {
                Log::warning("Pnedu {$label} cache invalidation failed", [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning("Pnedu {$label} cache invalidation error", [
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
