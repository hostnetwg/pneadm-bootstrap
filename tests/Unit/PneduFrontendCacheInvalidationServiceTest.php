<?php

namespace Tests\Unit;

use App\Services\PneduFrontendCacheInvalidationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PneduFrontendCacheInvalidationServiceTest extends TestCase
{
    public function test_posts_to_pnedu_internal_endpoint(): void
    {
        config([
            'services.pnedu.internal_url' => 'http://pnedu-app',
            'services.pnedu.internal_api_token' => 'test-internal-token',
        ]);

        Http::fake([
            'http://pnedu-app/api/internal/cache/upcoming-courses' => Http::response(['ok' => true], 200),
        ]);

        app(PneduFrontendCacheInvalidationService::class)->invalidateUpcomingCourses();

        Http::assertSent(function ($request) {
            return $request->url() === 'http://pnedu-app/api/internal/cache/upcoming-courses'
                && $request->hasHeader('Authorization', 'Bearer test-internal-token');
        });
    }

    public function test_skips_request_when_token_missing(): void
    {
        config([
            'services.pnedu.internal_url' => 'http://pnedu-app',
            'services.pnedu.internal_api_token' => '',
        ]);

        Http::fake();

        app(PneduFrontendCacheInvalidationService::class)->invalidateUpcomingCourses();

        Http::assertNothingSent();
    }
}
