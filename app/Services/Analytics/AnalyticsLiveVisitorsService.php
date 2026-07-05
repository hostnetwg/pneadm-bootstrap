<?php

namespace App\Services\Analytics;

use App\Models\Analytics\AnalyticsEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Agregacja „aktywnych teraz” odwiedzających pnedu.pl na podstawie ostatnich eventów lejka.
 * Bez PII — tylko analytics_session_id i etykiety podstron.
 */
class AnalyticsLiveVisitorsService
{
    /**
     * Eventy uznawane za sygnał obecności na stronie (lejek sprzedaży + kampanie).
     *
     * @var list<string>
     */
    private const TRACKED_EVENT_NAMES = [
        'campaign_short_link_visit',
        'campaign_redirect_resolved',
        'course_description_viewed',
        'order_form_viewed',
        'order_form_started',
        'order_form_section_interacted',
        'order_form_cta_clicked',
        'order_form_submit_clicked',
        'order_form_submit_attempted',
        'form_order_created',
    ];

    public function __construct(
        private readonly AnalyticsSessionJourneyService $journey,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     active_count: int,
     *     window_minutes: int,
     *     as_of: string,
     *     visitors: list<array{
     *         session_short: string,
     *         page_label: string,
     *         course_title: string|null,
     *         device_type: string|null,
     *         browser_family: string|null,
     *         last_seen_at: string,
     *         last_seen_ago_seconds: int,
     *         form_order_id: int|null,
     *         form_order_url: string|null,
     *         entry_referrer_domain: string|null,
     *         entry_campaign_code: string|null,
     *         journey_label: string,
     *         current_step_event_count: int,
     *         session_event_count: int,
     *         journey_steps: list<array{
     *             label: string,
     *             event_name: string,
     *             path: string|null,
     *             occurred_at: string|null,
     *             event_count: int
     *         }>
     *     }>
     * }
     */
    public function snapshot(): array
    {
        $enabled = (bool) config('analytics.live_visitors_dashboard.enabled', true);
        $windowMinutes = max(1, (int) config('analytics.live_visitors_dashboard.active_window_minutes', 30));
        $maxVisitors = max(1, (int) config('analytics.live_visitors_dashboard.max_listed', 12));
        $timezone = (string) config('analytics.live_visitors_dashboard.timezone', config('app.timezone', 'Europe/Warsaw'));
        $asOf = Carbon::now($timezone);

        if (! $enabled) {
            return [
                'enabled' => false,
                'active_count' => 0,
                'window_minutes' => $windowMinutes,
                'as_of' => $asOf->toIso8601String(),
                'visitors' => [],
            ];
        }

        $sinceUtc = Carbon::now('UTC')->subMinutes($windowMinutes);

        /** @var Collection<int, AnalyticsEvent> $recentEvents */
        $recentEvents = AnalyticsEvent::query()
            ->where('occurred_at', '>=', $sinceUtc->format('Y-m-d H:i:s'))
            ->whereNotNull('analytics_session_id')
            ->where('analytics_session_id', '!=', '')
            ->whereIn('event_name', self::TRACKED_EVENT_NAMES)
            ->orderByDesc('occurred_at')
            ->limit(500)
            ->get([
                'analytics_session_id',
                'event_name',
                'landing_target',
                'course_title_snapshot',
                'course_id',
                'path',
                'device_type',
                'browser_family',
                'occurred_at',
                'form_order_id',
                'metadata',
                'referrer_domain',
                'campaign_code',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'id',
            ]);

        $latestBySession = [];
        $eventsBySession = [];

        foreach ($recentEvents as $event) {
            $sessionId = (string) $event->analytics_session_id;
            if ($sessionId === '') {
                continue;
            }

            if (! isset($latestBySession[$sessionId])) {
                $latestBySession[$sessionId] = $event;
            }

            $eventsBySession[$sessionId][] = $event;
        }

        $visitors = collect($latestBySession)
            ->sortByDesc(fn (AnalyticsEvent $event) => $event->getRawOriginal('occurred_at'))
            ->take($maxVisitors)
            ->map(function (AnalyticsEvent $event) use ($timezone, $asOf, $eventsBySession): array {
                $sessionId = (string) $event->analytics_session_id;
                $sessionEvents = collect($eventsBySession[$sessionId] ?? [$event]);
                $journeySteps = $this->journey->buildStepsWithCounts($sessionEvents);
                $entry = $this->journey->buildEntry($sessionEvents);

                $lastSeenUtc = Carbon::parse((string) $event->getRawOriginal('occurred_at'), 'UTC');
                $lastSeenLocal = $lastSeenUtc->copy()->timezone($timezone);
                $formOrderId = $event->form_order_id !== null ? (int) $event->form_order_id : null;

                return [
                    'session_short' => $this->journey->shortSessionId($sessionId),
                    'page_label' => $this->journey->pageLabel($event),
                    'course_title' => filled($event->course_title_snapshot) ? (string) $event->course_title_snapshot : null,
                    'device_type' => $event->device_type,
                    'browser_family' => $event->browser_family,
                    'last_seen_at' => $lastSeenLocal->format('H:i:s'),
                    'last_seen_ago_seconds' => max(0, (int) $lastSeenUtc->diffInSeconds($asOf->copy()->utc())),
                    'form_order_id' => $formOrderId,
                    'form_order_url' => $formOrderId !== null
                        ? route('form-orders.show', $formOrderId)
                        : null,
                    'entry_referrer_domain' => $entry['referrer_domain'],
                    'entry_campaign_code' => $entry['campaign_code'],
                    'journey_label' => $this->journey->compactJourneyLabelWithCounts($journeySteps),
                    'current_step_event_count' => $this->journey->currentStepEventCount($journeySteps),
                    'session_event_count' => $sessionEvents->count(),
                    'journey_steps' => $journeySteps,
                ];
            })
            ->values()
            ->all();

        return [
            'enabled' => true,
            'active_count' => count($latestBySession),
            'window_minutes' => $windowMinutes,
            'as_of' => $asOf->toIso8601String(),
            'visitors' => $visitors,
        ];
    }

    /**
     * @deprecated Use AnalyticsSessionJourneyService::pageLabel()
     */
    public function pageLabel(AnalyticsEvent $event): string
    {
        return $this->journey->pageLabel($event);
    }
}
