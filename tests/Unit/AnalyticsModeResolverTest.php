<?php

namespace Tests\Unit;

use App\Enums\Analytics\AnalyticsEventName;
use App\Enums\Analytics\AnalyticsMode;
use App\Services\Analytics\AnalyticsModeResolver;
use Tests\TestCase;

class AnalyticsModeResolverTest extends TestCase
{
    public function test_it_resolves_off_when_analytics_is_disabled(): void
    {
        config()->set('analytics.enabled', false);

        $this->assertSame(AnalyticsMode::Off, (new AnalyticsModeResolver)->resolve());
    }

    public function test_aggregate_only_allows_only_aggregate_safe_events(): void
    {
        $resolver = new AnalyticsModeResolver;

        $this->assertTrue($resolver->shouldTrack(
            AnalyticsEventName::CourseDescriptionViewed,
            AnalyticsMode::AggregateOnly->value,
            'session-1',
        ));

        $this->assertFalse($resolver->shouldTrack(
            AnalyticsEventName::OrderFormValidationFailed,
            AnalyticsMode::AggregateOnly->value,
            'session-1',
        ));
    }

    public function test_sampling_is_deterministic_for_session_id(): void
    {
        $resolver = new AnalyticsModeResolver;

        $first = $resolver->passesSampling('same-session', 50);
        $second = $resolver->passesSampling('same-session', 50);

        $this->assertSame($first, $second);
        $this->assertFalse($resolver->passesSampling('same-session', 0));
        $this->assertTrue($resolver->passesSampling('same-session', 100));
    }
}
