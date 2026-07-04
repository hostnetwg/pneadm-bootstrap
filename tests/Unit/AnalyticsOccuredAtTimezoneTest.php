<?php

namespace Tests\Unit;

use App\Jobs\Analytics\StoreAnalyticsEventJob;
use App\Services\Analytics\AnalyticsModeResolver;
use App\Services\Analytics\AnalyticsPayloadSanitizer;
use App\Services\Analytics\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AnalyticsOccuredAtTimezoneTest extends TestCase
{
    public function test_track_defaults_occurred_at_to_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-04 22:00:00', 'Europe/Warsaw'));
        config(['app.timezone' => 'Europe/Warsaw']);

        Bus::fake();

        $resolver = $this->createMock(AnalyticsModeResolver::class);
        $resolver->method('shouldTrack')->willReturn(true);

        $service = new AnalyticsService($resolver, new AnalyticsPayloadSanitizer);
        $service->track('page_view', ['event_category' => 'navigation']);

        Bus::assertDispatched(StoreAnalyticsEventJob::class, function ($job) {
            return ($job->payload['occurred_at'] ?? '') === '2026-07-04 20:00:00';
        });

        Carbon::setTestNow();
    }
}
