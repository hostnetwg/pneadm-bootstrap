<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Analytics\AnalyticsEvent;
use App\Services\Analytics\AnalyticsDebugPayloadInspector;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AnalyticsDebugEventController extends Controller
{
    public function index(Request $request, AnalyticsDebugPayloadInspector $inspector): View
    {
        if (! config('analytics.debug_panel.enabled', false)) {
            abort(404);
        }

        $filters = $request->only([
            'event_name',
            'campaign_code',
            'course_id',
            'analytics_session_id',
            'order_form_session_id',
            'date_from',
            'date_to',
        ]);

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
            ])
            ->latest('occurred_at')
            ->latest('id');

        foreach (['event_name', 'campaign_code', 'analytics_session_id', 'order_form_session_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, trim((string) $request->query($field)));
            }
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', (int) $request->query('course_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('occurred_at', '>=', Carbon::parse((string) $request->query('date_from'))->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('occurred_at', '<=', Carbon::parse((string) $request->query('date_to'))->endOfDay());
        }

        $events = $query
            ->paginate(100)
            ->withQueryString();

        $eventWarnings = $events->getCollection()
            ->mapWithKeys(function (AnalyticsEvent $event) use ($inspector): array {
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

                return [$event->id => $inspector->forbiddenKeysIn($payload)];
            });

        return view('analytics.debug-events.index', [
            'events' => $events,
            'filters' => $filters,
            'eventWarnings' => $eventWarnings,
            'inspector' => $inspector,
        ]);
    }
}
