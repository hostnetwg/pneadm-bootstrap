<?php

namespace Tests\Unit;

use App\Enums\Analytics\AnalyticsEventName;
use App\Jobs\Analytics\StoreAnalyticsEventJob;
use App\Services\Analytics\AnalyticsModeResolver;
use App\Services\Analytics\AnalyticsPayloadSanitizer;
use App\Services\Analytics\AnalyticsService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    public function test_track_dispatches_store_job_on_analytics_queue_with_event_uuid(): void
    {
        config()->set('analytics.enabled', true);

        Queue::fake();

        $tracked = app(AnalyticsService::class)->track(AnalyticsEventName::CourseDescriptionViewed, [
            'analytics_session_id' => 'session-123',
            'course_id' => 10,
            'email' => 'secret@example.com',
        ]);

        $this->assertTrue($tracked);

        Queue::assertPushed(StoreAnalyticsEventJob::class, function (StoreAnalyticsEventJob $job): bool {
            return $job->connection === 'redis'
                && $job->queue === 'analytics'
                && isset($job->payload['event_uuid'])
                && $job->payload['event_name'] === AnalyticsEventName::CourseDescriptionViewed->value
                && ! array_key_exists('email', $job->payload);
        });
    }

    public function test_track_is_fail_silent_and_logs_without_personal_data(): void
    {
        Log::spy();

        $resolver = Mockery::mock(AnalyticsModeResolver::class);
        $resolver->shouldReceive('shouldTrack')->andThrow(new Exception('Redis secret@example.com failure'));

        $service = new AnalyticsService($resolver, new AnalyticsPayloadSanitizer);

        $tracked = $service->track(AnalyticsEventName::OrderFormViewed, [
            'email' => 'secret@example.com',
            'phone' => '123456789',
        ]);

        $this->assertFalse($tracked);

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'Analytics tracking skipped.'
                && ($context['event_name'] ?? null) === AnalyticsEventName::OrderFormViewed->value
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'secret@example.com')
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '123456789');
        });
    }
}
