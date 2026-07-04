<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Analytics\AnalyticsEvent;
use App\Services\Analytics\AnalyticsDebugPayloadInspector;
use App\Services\Analytics\AnalyticsDebugSessionThreadService;
use App\Support\UtcStorageDate;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AnalyticsDebugEventController extends Controller
{
    public function index(
        Request $request,
        AnalyticsDebugPayloadInspector $inspector,
        AnalyticsDebugSessionThreadService $threads,
    ): View {
        if (! config('analytics.debug_panel.enabled', false)) {
            abort(404);
        }

        $layout = (string) $request->query('layout', 'threads');
        if (! in_array($layout, ['threads', 'flat'], true)) {
            $layout = 'threads';
        }

        $filters = $request->only([
            'event_name',
            'campaign_code',
            'course_id',
            'analytics_session_id',
            'order_form_session_id',
            'date_from',
            'date_to',
            'layout',
        ]);
        $filters['layout'] = $layout;

        $query = $this->filteredEventsQuery($request);

        if ($layout === 'threads') {
            $threadData = $threads->paginateThreads($query, 25);
            $eventWarnings = $this->eventWarningsFor(
                collect($threadData['threads']->items())
                    ->flatMap(fn (array $thread): array => $thread['events'])
                    ->merge($threadData['orphan_events']),
                $inspector,
            );

            return view('analytics.debug-events.index', [
                'layout' => $layout,
                'threads' => $threadData['threads'],
                'orphanEvents' => $threadData['orphan_events'],
                'events' => null,
                'filters' => $filters,
                'eventWarnings' => $eventWarnings,
                'inspector' => $inspector,
            ]);
        }

        $events = $query
            ->paginate(100)
            ->withQueryString();

        $eventWarnings = $this->eventWarningsFor($events->getCollection(), $inspector);

        return view('analytics.debug-events.index', [
            'layout' => $layout,
            'threads' => null,
            'orphanEvents' => [],
            'events' => $events,
            'filters' => $filters,
            'eventWarnings' => $eventWarnings,
            'inspector' => $inspector,
        ]);
    }

    private function filteredEventsQuery(Request $request): Builder
    {
        $query = AnalyticsEvent::query()
            ->select([
                'id',
                'occurred_at',
                'event_name',
                'event_category',
                'analytics_session_id',
                'order_form_session_id',
                'course_id',
                'course_title_snapshot',
                'campaign_code',
                'landing_target',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'route_name',
                'path',
                'referrer_domain',
                'device_type',
                'metadata',
                'created_at',
            ]);

        foreach (['event_name', 'campaign_code', 'analytics_session_id', 'order_form_session_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, trim((string) $request->query($field)));
            }
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', (int) $request->query('course_id'));
        }

        if ($request->filled('date_from')) {
            $fromUtc = UtcStorageDate::dayStartUtc((string) $request->query('date_from'));
            $query->where('occurred_at', '>=', $fromUtc->format('Y-m-d H:i:s'));
        }

        if ($request->filled('date_to')) {
            $toUtc = UtcStorageDate::dayEndUtc((string) $request->query('date_to'));
            $query->where('occurred_at', '<=', $toUtc->format('Y-m-d H:i:s'));
        }

        return $query;
    }

    /**
     * @param  iterable<int, AnalyticsEvent>  $events
     * @return array<int, list<string>>
     */
    private function eventWarningsFor(iterable $events, AnalyticsDebugPayloadInspector $inspector): array
    {
        $warnings = [];

        foreach ($events as $event) {
            if (! $event instanceof AnalyticsEvent) {
                continue;
            }

            $payload = array_filter([
                'event_name' => $event->event_name,
                'event_category' => $event->event_category,
                'analytics_session_id' => $event->analytics_session_id,
                'order_form_session_id' => $event->order_form_session_id,
                'course_id' => $event->course_id,
                'course_title_snapshot' => $event->course_title_snapshot,
                'campaign_code' => $event->campaign_code,
                'landing_target' => $event->landing_target,
                'utm_source' => $event->utm_source,
                'utm_medium' => $event->utm_medium,
                'utm_campaign' => $event->utm_campaign,
                'route_name' => $event->route_name,
                'path' => $event->path,
                'referrer_domain' => $event->referrer_domain,
                'device_type' => $event->device_type,
                'metadata_json' => $event->metadata ?? [],
            ], static fn ($value): bool => $value !== null && $value !== '');

            $warnings[$event->id] = $inspector->forbiddenKeysIn($payload);
        }

        return $warnings;
    }
}
