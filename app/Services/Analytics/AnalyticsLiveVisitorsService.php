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
    ];

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
     *         last_seen_ago_seconds: int
     *     }>
     * }
     */
    public function snapshot(): array
    {
        $enabled = (bool) config('analytics.live_visitors_dashboard.enabled', true);
        $windowMinutes = max(1, (int) config('analytics.live_visitors_dashboard.active_window_minutes', 5));
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
                'path',
                'device_type',
                'browser_family',
                'occurred_at',
            ]);

        $latestBySession = [];
        foreach ($recentEvents as $event) {
            $sessionId = (string) $event->analytics_session_id;
            if ($sessionId === '' || isset($latestBySession[$sessionId])) {
                continue;
            }
            $latestBySession[$sessionId] = $event;
        }

        $visitors = collect($latestBySession)
            ->sortByDesc(fn (AnalyticsEvent $event) => $event->getRawOriginal('occurred_at'))
            ->take($maxVisitors)
            ->map(function (AnalyticsEvent $event) use ($timezone, $asOf): array {
                $lastSeenUtc = Carbon::parse((string) $event->getRawOriginal('occurred_at'), 'UTC');
                $lastSeenLocal = $lastSeenUtc->copy()->timezone($timezone);

                return [
                    'session_short' => $this->shortSessionId((string) $event->analytics_session_id),
                    'page_label' => $this->pageLabel($event),
                    'course_title' => filled($event->course_title_snapshot) ? (string) $event->course_title_snapshot : null,
                    'device_type' => $event->device_type,
                    'browser_family' => $event->browser_family,
                    'last_seen_at' => $lastSeenLocal->format('H:i:s'),
                    'last_seen_ago_seconds' => max(0, (int) $lastSeenUtc->diffInSeconds($asOf->copy()->utc())),
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

    public function pageLabel(AnalyticsEvent $event): string
    {
        return match ($event->event_name) {
            'course_description_viewed' => 'Opis szkolenia',
            'order_form_viewed' => 'Formularz zamówienia',
            'order_form_started',
            'order_form_section_interacted',
            'order_form_cta_clicked',
            'order_form_submit_clicked',
            'order_form_submit_attempted' => 'Formularz — aktywny',
            'campaign_short_link_visit' => 'Link kampanii',
            'campaign_redirect_resolved' => 'Przekierowanie kampanii',
            default => match ($event->landing_target) {
                'course_description' => 'Opis szkolenia',
                'order_form_direct' => 'Formularz zamówienia',
                default => filled($event->path) ? (string) $event->path : 'Lejek sprzedaży',
            },
        };
    }

    private function shortSessionId(string $sessionId): string
    {
        $sessionId = trim($sessionId);

        if (strlen($sessionId) <= 8) {
            return $sessionId;
        }

        return '…'.substr($sessionId, -4);
    }
}
