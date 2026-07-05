<?php

namespace Tests\Unit;

use App\Models\Analytics\AnalyticsEvent;
use App\Services\Analytics\AnalyticsSessionJourneyService;
use Tests\TestCase;

class AnalyticsSessionJourneyServiceTest extends TestCase
{
    private AnalyticsSessionJourneyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AnalyticsSessionJourneyService::class);
    }

    public function test_build_steps_collapses_repeated_page_labels_and_keeps_order(): void
    {
        $events = collect([
            new AnalyticsEvent([
                'event_name' => 'campaign_short_link_visit',
                'path' => '/c/abc',
                'occurred_at' => '2026-07-04 10:00:00',
                'id' => 1,
            ]),
            new AnalyticsEvent([
                'event_name' => 'course_description_viewed',
                'path' => '/courses/1',
                'occurred_at' => '2026-07-04 10:01:00',
                'id' => 2,
            ]),
            new AnalyticsEvent([
                'event_name' => 'order_form_viewed',
                'path' => '/courses/1/order',
                'occurred_at' => '2026-07-04 10:02:00',
                'id' => 3,
            ]),
            new AnalyticsEvent([
                'event_name' => 'order_form_section_interacted',
                'path' => '/courses/1/order',
                'occurred_at' => '2026-07-04 10:03:00',
                'id' => 4,
            ]),
        ]);

        $steps = $this->service->buildSteps($events);

        $this->assertSame([
            'Link kampanii',
            'Opis szkolenia',
            'Formularz zamówienia',
            'Formularz — aktywny',
        ], array_column($steps, 'label'));
    }

    public function test_build_entry_uses_first_event_in_session(): void
    {
        $events = collect([
            new AnalyticsEvent([
                'event_name' => 'campaign_short_link_visit',
                'referrer_domain' => 'facebook.com',
                'campaign_code' => 'fb-spring',
                'path' => '/c/abc',
                'occurred_at' => '2026-07-04 10:00:00',
                'id' => 1,
            ]),
            new AnalyticsEvent([
                'event_name' => 'course_description_viewed',
                'referrer_domain' => null,
                'path' => '/courses/1',
                'occurred_at' => '2026-07-04 10:05:00',
                'id' => 2,
            ]),
        ]);

        $entry = $this->service->buildEntry($events);

        $this->assertSame('facebook.com', $entry['referrer_domain']);
        $this->assertSame('fb-spring', $entry['campaign_code']);
        $this->assertSame('Link kampanii', $entry['page_label']);
    }

    public function test_compact_journey_label_joins_steps(): void
    {
        $label = $this->service->compactJourneyLabel([
            ['label' => 'Opis szkolenia'],
            ['label' => 'Formularz zamówienia'],
        ]);

        $this->assertSame('Opis szkolenia → Formularz zamówienia', $label);
    }

    public function test_build_steps_with_counts_groups_events_per_step(): void
    {
        $events = collect([
            new AnalyticsEvent([
                'event_name' => 'order_form_viewed',
                'path' => '/courses/1/order',
                'course_id' => 1,
                'occurred_at' => '2026-07-04 10:00:00',
                'id' => 1,
            ]),
            new AnalyticsEvent([
                'event_name' => 'order_form_section_interacted',
                'path' => '/courses/1/order',
                'course_id' => 1,
                'occurred_at' => '2026-07-04 10:01:00',
                'id' => 2,
            ]),
            new AnalyticsEvent([
                'event_name' => 'order_form_section_interacted',
                'path' => '/courses/1/order',
                'course_id' => 1,
                'occurred_at' => '2026-07-04 10:02:00',
                'id' => 3,
            ]),
            new AnalyticsEvent([
                'event_name' => 'order_form_cta_clicked',
                'path' => '/courses/1/order',
                'course_id' => 1,
                'occurred_at' => '2026-07-04 10:03:00',
                'id' => 4,
            ]),
        ]);

        $steps = $this->service->buildStepsWithCounts($events);

        $this->assertSame(2, count($steps));
        $this->assertSame(1, $steps[0]['event_count']);
        $this->assertSame('Formularz zamówienia', $steps[0]['label']);
        $this->assertSame(3, $steps[1]['event_count']);
        $this->assertSame('Formularz — aktywny', $steps[1]['label']);
        $this->assertSame(
            'Formularz zamówienia → Formularz — aktywny (3)',
            $this->service->compactJourneyLabelWithCurrentCount($steps),
        );
    }
}

