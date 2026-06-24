<?php

namespace Tests\Unit;

use App\Jobs\Analytics\StoreAnalyticsEventJob;
use App\Services\Analytics\AnalyticsPayloadSanitizer;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StoreAnalyticsEventJobTest extends TestCase
{
    public function test_handle_is_fail_silent_when_analytics_database_is_unavailable(): void
    {
        config()->set('database.connections.analytics.database', '__missing_analytics_database__');

        Log::spy();

        $job = new StoreAnalyticsEventJob([
            'event_uuid' => '7d7b69fe-43d8-4ba9-9db8-4d3b8f78c001',
            'event_name' => 'course_description_viewed',
            'event_category' => 'landing',
            'occurred_at' => now()->toDateTimeString(),
            'email' => 'secret@example.com',
        ]);

        $job->handle(new AnalyticsPayloadSanitizer);

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'Analytics event was not stored.'
                && ($context['event_name'] ?? null) === 'course_description_viewed'
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'secret@example.com');
        });
    }
}
