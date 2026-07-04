<?php

namespace App\Services\Analytics;

use App\Models\Analytics\AnalyticsEvent;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class AnalyticsDebugSessionThreadService
{
    public function __construct(
        private readonly AnalyticsSessionJourneyService $journey,
    ) {}

    /**
     * @return array{
     *     threads: LengthAwarePaginator<int, array{
     *         analytics_session_id: string,
     *         session_short: string,
     *         event_count: int,
     *         first_occurred_at: string|null,
     *         last_occurred_at: string|null,
     *         entry: array<string, string|null>,
     *         journey_steps: list<array<string, string|null>>,
     *         journey_label: string,
     *         events: list<AnalyticsEvent>
     *     }>,
     *     orphan_events: list<AnalyticsEvent>
     * }
     */
    public function paginateThreads(Builder $filteredQuery, int $perPage = 25): array
    {
        $sessionRows = (clone $filteredQuery)
            ->whereNotNull('analytics_session_id')
            ->where('analytics_session_id', '!=', '')
            ->select('analytics_session_id')
            ->selectRaw('MIN(occurred_at) as first_occurred_at, MAX(occurred_at) as last_occurred_at, COUNT(*) as event_count')
            ->groupBy('analytics_session_id')
            ->orderByDesc('last_occurred_at')
            ->orderByDesc('analytics_session_id')
            ->paginate($perPage);

        $sessionIds = collect($sessionRows->items())
            ->pluck('analytics_session_id')
            ->filter()
            ->values()
            ->all();

        $eventsBySession = $this->eventsForSessions($filteredQuery, $sessionIds);

        $threads = collect($sessionRows->items())
            ->map(function (object $row) use ($eventsBySession): array {
                $sessionId = (string) $row->analytics_session_id;
                /** @var Collection<int, AnalyticsEvent> $sessionEvents */
                $sessionEvents = $eventsBySession->get($sessionId, collect());
                $journeySteps = $this->journey->buildSteps($sessionEvents);

                return [
                    'analytics_session_id' => $sessionId,
                    'session_short' => $this->journey->shortSessionId($sessionId),
                    'event_count' => (int) ($row->event_count ?? $sessionEvents->count()),
                    'first_occurred_at' => $this->formatUtc((string) ($row->first_occurred_at ?? '')),
                    'last_occurred_at' => $this->formatUtc((string) ($row->last_occurred_at ?? '')),
                    'entry' => $this->journey->buildEntry($sessionEvents),
                    'journey_steps' => $journeySteps,
                    'journey_label' => $this->journey->compactJourneyLabel($journeySteps),
                    'events' => $sessionEvents->values()->all(),
                ];
            });

        $paginatedThreads = new Paginator(
            $threads,
            $sessionRows->total(),
            $sessionRows->perPage(),
            $sessionRows->currentPage(),
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $sessionRows->getPageName(),
            ],
        );
        $paginatedThreads->withQueryString();

        $orphanEvents = (clone $filteredQuery)
            ->where(function (Builder $query): void {
                $query->whereNull('analytics_session_id')
                    ->orWhere('analytics_session_id', '=', '');
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return [
            'threads' => $paginatedThreads,
            'orphan_events' => $orphanEvents->all(),
        ];
    }

    /**
     * @param  list<string>  $sessionIds
     * @return Collection<string, Collection<int, AnalyticsEvent>>
     */
    private function eventsForSessions(Builder $filteredQuery, array $sessionIds): Collection
    {
        if ($sessionIds === []) {
            return collect();
        }

        return (clone $filteredQuery)
            ->whereIn('analytics_session_id', $sessionIds)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (AnalyticsEvent $event): string => (string) $event->analytics_session_id);
    }

    private function formatUtc(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        return Carbon::parse($value, 'UTC')->toIso8601String();
    }
}
