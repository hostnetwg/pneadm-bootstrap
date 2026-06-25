<?php

namespace App\Services\Analytics;

use App\Enums\Analytics\AnalyticsEventName;
use App\Models\Analytics\AnalyticsDailyCampaignAbandonmentStat;
use App\Models\Analytics\AnalyticsDailyFormAbandonmentStat;
use App\Models\Analytics\AnalyticsEvent;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AnalyticsAbandonmentAggregationService
{
    /**
     * Eventy JS z B2 (nie ma ich w enumie pneadm — pochodzą z pnedu, w bazie to zwykłe stringi).
     */
    private const EVENT_FORM_STARTED = 'order_form_started';

    private const EVENT_SUBMIT_CLICKED = 'order_form_submit_clicked';

    /**
     * @return array{course_rows: int, campaign_rows: int, dates: list<string>}
     */
    public function aggregateForDate(Carbon|string $statDate): array
    {
        $date = $this->normalizeStatDate($statDate);

        return $this->aggregate($date);
    }

    /**
     * @return array{course_rows: int, campaign_rows: int, dates: list<string>}
     */
    public function aggregateForDateRange(Carbon|string $from, Carbon|string $to): array
    {
        $start = $this->normalizeStatDate($from);
        $end = $this->normalizeStatDate($to);

        if ($start->greaterThan($end)) {
            throw new \InvalidArgumentException('Data początkowa nie może być późniejsza niż data końcowa.');
        }

        $courseRows = 0;
        $campaignRows = 0;
        $dates = [];

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $result = $this->aggregate($this->normalizeStatDate($date));
            $courseRows += $result['course_rows'];
            $campaignRows += $result['campaign_rows'];
            $dates[] = $result['dates'][0];
        }

        return [
            'course_rows' => $courseRows,
            'campaign_rows' => $campaignRows,
            'dates' => $dates,
        ];
    }

    public function defaultStatDate(): Carbon
    {
        $lag = max(1, (int) config('analytics.abandonment.aggregation_lag_days', 2));

        return Carbon::now($this->timezone())->subDays($lag)->startOfDay();
    }

    public function timezone(): string
    {
        return (string) config('analytics.abandonment.timezone', config('analytics.aggregation.timezone', 'Europe/Warsaw'));
    }

    /**
     * @return array{course_rows: int, campaign_rows: int, dates: list<string>}
     */
    private function aggregate(Carbon $statDate): array
    {
        $dateString = $statDate->toDateString();
        [$rangeStart, $rangeEnd] = $this->dayBoundsInUtc($statDate);

        AnalyticsDailyFormAbandonmentStat::query()->whereDate('stat_date', $dateString)->delete();
        AnalyticsDailyCampaignAbandonmentStat::query()->whereDate('stat_date', $dateString)->delete();

        $sessionIds = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [$rangeStart, $rangeEnd])
            ->whereNotNull('order_form_session_id')
            ->whereIn('event_name', $this->funnelEventNames())
            ->distinct()
            ->pluck('order_form_session_id')
            ->all();

        if ($sessionIds === []) {
            return ['course_rows' => 0, 'campaign_rows' => 0, 'dates' => [$dateString]];
        }

        $courseAggregates = [];
        $campaignAggregates = [];

        foreach (array_chunk($sessionIds, 500) as $chunk) {
            $events = AnalyticsEvent::query()
                ->whereIn('order_form_session_id', $chunk)
                ->whereIn('event_name', $this->funnelEventNames())
                ->get(['order_form_session_id', 'event_name', 'occurred_at', 'course_id', 'course_title_snapshot', 'campaign_code', 'campaign_id']);

            foreach ($events->groupBy('order_form_session_id') as $sessionEvents) {
                $session = $this->summarizeSession($sessionEvents);

                // Sesję liczymy raz, w dniu jej pierwszego eventu formularza.
                if ($session['first_event_date'] !== $dateString) {
                    continue;
                }

                $bucket = $this->classifyBucket($session);

                if ($session['course_id'] !== null) {
                    $this->applySession($courseAggregates, $session['course_id'], $session, $bucket, [
                        'course_id' => $session['course_id'],
                        'course_title_snapshot' => $session['course_title_snapshot'],
                    ]);
                }

                if ($session['campaign_code'] !== null) {
                    $this->applySession($campaignAggregates, $session['campaign_code'], $session, $bucket, [
                        'campaign_code' => $session['campaign_code'],
                        'campaign_id' => $session['campaign_id'],
                    ]);
                }
            }
        }

        $now = now();

        foreach ($courseAggregates as $row) {
            AnalyticsDailyFormAbandonmentStat::query()->create(array_merge($row, [
                'stat_date' => $dateString,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        foreach ($campaignAggregates as $row) {
            AnalyticsDailyCampaignAbandonmentStat::query()->create(array_merge($row, [
                'stat_date' => $dateString,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        return [
            'course_rows' => count($courseAggregates),
            'campaign_rows' => count($campaignAggregates),
            'dates' => [$dateString],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AnalyticsEvent>  $sessionEvents
     * @return array<string, mixed>
     */
    private function summarizeSession($sessionEvents): array
    {
        $firstEventAt = null;
        $courseId = null;
        $courseTitle = null;

        // Atrybucja kampanii = first-touch: pierwsza (wg occurred_at) niepusta kampania w sesji.
        // Porzucenia raportujemy kohortowo wg dnia pierwszego eventu, więc kampania ma odpowiadać
        // źródłu wejścia do formularza, a nie późniejszej aktywności w tej samej sesji.
        $campaignFirstAt = null;
        $campaignCode = null;
        $campaignId = null;

        $reached = [
            'viewed' => false,
            'started' => false,
            'submit_clicked' => false,
            'submit_attempted' => false,
            'created' => false,
        ];

        foreach ($sessionEvents as $event) {
            // Kolumna occurred_at jest zapisywana w UTC (inwariant produkcyjny). Czytamy
            // surową wartość jako UTC, żeby nie zależeć od strefy castowania modelu.
            $occurredAt = Carbon::parse((string) $event->getRawOriginal('occurred_at'), 'UTC');

            if ($firstEventAt === null || $occurredAt->lessThan($firstEventAt)) {
                $firstEventAt = $occurredAt;
            }

            if ($courseId === null && $event->course_id !== null && (int) $event->course_id > 0) {
                $courseId = (int) $event->course_id;
            }

            if ($courseTitle === null && filled($event->course_title_snapshot)) {
                $courseTitle = $event->course_title_snapshot;
            }

            $code = trim((string) ($event->campaign_code ?? ''));
            if ($code !== '' && ($campaignFirstAt === null || $occurredAt->lessThan($campaignFirstAt))) {
                $campaignFirstAt = $occurredAt;
                $campaignCode = $code;
                $campaignId = $event->campaign_id !== null ? (int) $event->campaign_id : null;
            }

            $this->markReached($reached, (string) $event->event_name);
        }

        $firstEventAt ??= now();

        return [
            'first_event_date' => $firstEventAt->copy()->timezone($this->timezone())->toDateString(),
            'course_id' => $courseId,
            'course_title_snapshot' => $courseTitle,
            'campaign_code' => $campaignCode,
            'campaign_id' => $campaignId,
            'reached' => $reached,
        ];
    }

    /**
     * @param  array<string, bool>  $reached
     */
    private function markReached(array &$reached, string $eventName): void
    {
        match ($eventName) {
            AnalyticsEventName::OrderFormViewed->value => $reached['viewed'] = true,
            self::EVENT_FORM_STARTED => $reached['started'] = true,
            self::EVENT_SUBMIT_CLICKED => $reached['submit_clicked'] = true,
            AnalyticsEventName::OrderFormSubmitAttempted->value => $reached['submit_attempted'] = true,
            AnalyticsEventName::FormOrderCreated->value => $reached['created'] = true,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function classifyBucket(array $session): string
    {
        $reached = $session['reached'];

        return match (true) {
            $reached['created'] => 'converted',
            $reached['submit_attempted'] => 'submit_attempted_not_created',
            $reached['submit_clicked'] => 'submit_clicked_not_attempted',
            $reached['started'] => 'started_not_submit_clicked',
            default => 'viewed_not_started',
        };
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $aggregates
     * @param  array<string, mixed>  $session
     * @param  array<string, mixed>  $identity
     */
    private function applySession(array &$aggregates, int|string $key, array $session, string $bucket, array $identity): void
    {
        if (! isset($aggregates[$key])) {
            $aggregates[$key] = array_merge($identity, $this->emptyAggregate());
        }

        // Snapshoty mogą się doprecyzować przy kolejnych sesjach (np. tytuł kursu).
        foreach ($identity as $field => $value) {
            if ($value !== null && $value !== '') {
                $aggregates[$key][$field] = $value;
            }
        }

        $aggregates[$key]['sessions_total']++;
        $aggregates[$key][$bucket]++;

        $reached = $session['reached'];
        foreach (['viewed', 'started', 'submit_clicked', 'submit_attempted', 'created'] as $stage) {
            if ($reached[$stage]) {
                $aggregates[$key]['reached_'.$stage]++;
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function emptyAggregate(): array
    {
        return [
            'sessions_total' => 0,
            'reached_viewed' => 0,
            'reached_started' => 0,
            'reached_submit_clicked' => 0,
            'reached_submit_attempted' => 0,
            'reached_created' => 0,
            'viewed_not_started' => 0,
            'started_not_submit_clicked' => 0,
            'submit_clicked_not_attempted' => 0,
            'submit_attempted_not_created' => 0,
            'converted' => 0,
        ];
    }

    /**
     * @return list<string>
     */
    private function funnelEventNames(): array
    {
        return [
            AnalyticsEventName::OrderFormViewed->value,
            self::EVENT_FORM_STARTED,
            self::EVENT_SUBMIT_CLICKED,
            AnalyticsEventName::OrderFormSubmitAttempted->value,
            AnalyticsEventName::FormOrderCreated->value,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dayBoundsInUtc(Carbon $statDate): array
    {
        $timezone = $this->timezone();
        $localDate = $this->normalizeStatDate($statDate);

        return [
            $localDate->copy()->timezone($timezone)->startOfDay()->utc(),
            $localDate->copy()->timezone($timezone)->endOfDay()->utc(),
        ];
    }

    private function normalizeStatDate(Carbon|string $statDate): Carbon
    {
        if ($statDate instanceof Carbon) {
            return $statDate->copy()->timezone($this->timezone())->startOfDay();
        }

        return Carbon::parse($statDate, $this->timezone())->startOfDay();
    }
}
