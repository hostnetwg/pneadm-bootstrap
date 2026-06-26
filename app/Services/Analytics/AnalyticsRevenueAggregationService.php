<?php

namespace App\Services\Analytics;

use App\Enums\Analytics\AnalyticsEventName;
use App\Models\Analytics\AnalyticsDailyCampaignRevenueStat;
use App\Models\Analytics\AnalyticsDailyCourseRevenueStat;
use App\Models\Analytics\AnalyticsEvent;
use App\Models\FormOrder;
use App\Models\MarketingCampaign;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Etap R1 — agregaty rozliczeń płatności/faktur.
 *
 * Czyta surowe eventy rozliczeniowe z analytics_events i liczy dzienne agregaty
 * per kurs i per kampania WG DATY EVENTU (Europe/Warsaw):
 *  - form_order_created                                   → orders_created / ordered_revenue_gross
 *  - payment_status_changed (paid + online)              → online_paid_orders / online_paid_revenue_gross
 *  - invoice_created (order_flow=deferred)               → deferred_invoiced_orders / deferred_invoiced_revenue_gross
 *  - invoice_created (order_flow=online)                 → online_invoiced_marker_orders (NIE settled)
 *
 * settled = online_paid + deferred_invoiced (materializowane). Zero PII (ADR-005).
 * Atrybucja kampanii: campaign_code z eventu, fallback FormOrder.fb_source (po form_order_id).
 */
class AnalyticsRevenueAggregationService
{
    /**
     * @return array{course_rows: int, campaign_rows: int, dates: list<string>}
     */
    public function aggregateForDate(Carbon|string $date, bool $force = false): array
    {
        return $this->aggregate($this->normalizeStatDate($date));
    }

    /**
     * @return array{course_rows: int, campaign_rows: int, dates: list<string>}
     */
    public function aggregateForDateRange(Carbon|string $from, Carbon|string $to, bool $force = false): array
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
        $lag = max(0, (int) config('analytics.revenue.aggregation_lag_days', 1));

        return Carbon::now($this->timezone())->subDays($lag)->startOfDay();
    }

    public function timezone(): string
    {
        return (string) config('analytics.revenue.timezone', config('analytics.aggregation.timezone', 'Europe/Warsaw'));
    }

    /**
     * @return array{course_rows: int, campaign_rows: int, dates: list<string>}
     */
    private function aggregate(Carbon $statDate): array
    {
        $dateString = $statDate->toDateString();
        [$rangeStart, $rangeEnd] = $this->dayBoundsInUtc($statDate);

        AnalyticsDailyCourseRevenueStat::query()->whereDate('stat_date', $dateString)->delete();
        AnalyticsDailyCampaignRevenueStat::query()->whereDate('stat_date', $dateString)->delete();

        $events = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [$rangeStart, $rangeEnd])
            ->whereIn('event_name', $this->revenueEventNames())
            ->get(['event_name', 'occurred_at', 'course_id', 'course_title_snapshot', 'campaign_code', 'campaign_id', 'form_order_id', 'metadata']);

        if ($events->isEmpty()) {
            return ['course_rows' => 0, 'campaign_rows' => 0, 'dates' => [$dateString]];
        }

        $formOrders = $this->loadFormOrders($events);

        $courseAggregates = [];
        $campaignAggregates = [];

        foreach ($events as $event) {
            $contribution = $this->contribution($event);

            // Eventy, które nie wnoszą żadnej metryki (np. payment_status != paid), pomijamy.
            if ($contribution === null) {
                continue;
            }

            $formOrder = $this->resolveFormOrder($event, $formOrders);

            $courseId = $this->resolveCourseId($event, $formOrder);
            $campaign = $this->resolveCampaign($event, $formOrder);

            if ($courseId !== null) {
                $this->applyCourse(
                    $courseAggregates,
                    $courseId,
                    $this->resolveCourseTitle($event, $formOrder),
                    $contribution,
                    $campaign === null,
                );
            }

            if ($campaign !== null) {
                $this->applyCampaign($campaignAggregates, $campaign, $contribution);
            }
        }

        $this->persist($courseAggregates, $campaignAggregates, $dateString);

        return [
            'course_rows' => count($courseAggregates),
            'campaign_rows' => count($campaignAggregates),
            'dates' => [$dateString],
        ];
    }

    /**
     * Zwraca wkład metryczny eventu lub null, jeśli event nie liczy się do żadnej metryki.
     *
     * @return array<string, int|float>|null
     */
    private function contribution(AnalyticsEvent $event): ?array
    {
        $metadata = is_array($event->metadata) ? $event->metadata : [];
        $amount = $this->extractAmountGross($metadata);
        $orderFlow = $this->stringOrNull($metadata['order_flow'] ?? null);

        $contribution = $this->emptyContribution();

        switch ((string) $event->event_name) {
            case AnalyticsEventName::FormOrderCreated->value:
                $contribution['orders_created'] = 1;
                $contribution['ordered_revenue_gross'] = $amount;

                return $contribution;

            case AnalyticsEventName::PaymentStatusChanged->value:
                $paymentStatus = $this->stringOrNull($metadata['payment_status'] ?? null);
                if ($paymentStatus === 'paid' && $orderFlow === 'online') {
                    $contribution['online_paid_orders'] = 1;
                    $contribution['online_paid_revenue_gross'] = $amount;

                    return $contribution;
                }

                return null;

            case AnalyticsEventName::InvoiceCreated->value:
                if ($orderFlow === 'deferred') {
                    $contribution['deferred_invoiced_orders'] = 1;
                    $contribution['deferred_invoiced_revenue_gross'] = $amount;

                    return $contribution;
                }

                if ($orderFlow === 'online') {
                    // Marker księgowy — NIE wchodzi do deferred ani settled.
                    $contribution['online_invoiced_marker_orders'] = 1;

                    return $contribution;
                }

                return null;

            default:
                return null;
        }
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $aggregates
     * @param  array<string, int|float>  $contribution
     */
    private function applyCourse(array &$aggregates, int $courseId, ?string $courseTitle, array $contribution, bool $withoutCampaign): void
    {
        if (! isset($aggregates[$courseId])) {
            $aggregates[$courseId] = array_merge(
                ['course_id' => $courseId, 'course_title_snapshot' => null],
                $this->emptyContribution(),
                [
                    'orders_created_without_campaign' => 0,
                    'online_paid_without_campaign' => 0,
                    'deferred_invoiced_without_campaign' => 0,
                ],
            );
        }

        if ($courseTitle !== null && $courseTitle !== '') {
            $aggregates[$courseId]['course_title_snapshot'] = $courseTitle;
        }

        $this->addContribution($aggregates[$courseId], $contribution);

        if ($withoutCampaign) {
            if (($contribution['orders_created'] ?? 0) > 0) {
                $aggregates[$courseId]['orders_created_without_campaign']++;
            }
            if (($contribution['online_paid_orders'] ?? 0) > 0) {
                $aggregates[$courseId]['online_paid_without_campaign']++;
            }
            if (($contribution['deferred_invoiced_orders'] ?? 0) > 0) {
                $aggregates[$courseId]['deferred_invoiced_without_campaign']++;
            }
        }
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $aggregates
     * @param  array{code: string, id: int|null}  $campaign
     * @param  array<string, int|float>  $contribution
     */
    private function applyCampaign(array &$aggregates, array $campaign, array $contribution): void
    {
        $key = $campaign['code'];

        if (! isset($aggregates[$key])) {
            $aggregates[$key] = array_merge(
                ['campaign_code' => $campaign['code'], 'campaign_id' => $campaign['id']],
                $this->emptyContribution(),
            );
        }

        if ($campaign['id'] !== null) {
            $aggregates[$key]['campaign_id'] = $campaign['id'];
        }

        $this->addContribution($aggregates[$key], $contribution);
    }

    /**
     * @param  array<string, mixed>  $aggregate
     * @param  array<string, int|float>  $contribution
     */
    private function addContribution(array &$aggregate, array $contribution): void
    {
        foreach ($contribution as $field => $value) {
            $aggregate[$field] += $value;
        }
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $courseAggregates
     * @param  array<int|string, array<string, mixed>>  $campaignAggregates
     */
    private function persist(array $courseAggregates, array $campaignAggregates, string $dateString): void
    {
        $now = now();

        foreach ($courseAggregates as $row) {
            $row = $this->materializeSettled($row);
            AnalyticsDailyCourseRevenueStat::query()->create(array_merge($row, [
                'stat_date' => $dateString,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        foreach ($campaignAggregates as $row) {
            $row = $this->materializeSettled($row);
            AnalyticsDailyCampaignRevenueStat::query()->create(array_merge($row, [
                'stat_date' => $dateString,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function materializeSettled(array $row): array
    {
        $row['settled_orders_total'] = (int) $row['online_paid_orders'] + (int) $row['deferred_invoiced_orders'];
        $row['settled_revenue_gross'] = round((float) $row['online_paid_revenue_gross'] + (float) $row['deferred_invoiced_revenue_gross'], 2);

        return $row;
    }

    /**
     * Batch lookup FormOrder (bez N+1, bez PII) — tylko id/fb_source/product_id/product_name.
     *
     * @param  Collection<int, AnalyticsEvent>  $events
     * @return array<int, object{fb_source: ?string, product_id: ?int, product_name: ?string}>
     */
    private function loadFormOrders(Collection $events): array
    {
        $ids = $events
            ->pluck('form_order_id')
            ->filter(fn ($id): bool => $id !== null && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        try {
            return FormOrder::query()
                ->whereIn('id', $ids)
                ->get(['id', 'fb_source', 'product_id', 'product_name'])
                ->keyBy('id')
                ->all();
        } catch (Throwable) {
            // Fail-safe: brak tabeli/uprawnień nie może przerwać agregacji.
            return [];
        }
    }

    /**
     * @param  array<int, object>  $formOrders
     */
    private function resolveFormOrder(AnalyticsEvent $event, array $formOrders): ?object
    {
        $formOrderId = $event->form_order_id !== null ? (int) $event->form_order_id : 0;

        return $formOrders[$formOrderId] ?? null;
    }

    private function resolveCourseId(AnalyticsEvent $event, ?object $formOrder): ?int
    {
        if ($event->course_id !== null && (int) $event->course_id > 0) {
            return (int) $event->course_id;
        }

        if ($formOrder !== null && $formOrder->product_id !== null && (int) $formOrder->product_id > 0) {
            return (int) $formOrder->product_id;
        }

        return null;
    }

    private function resolveCourseTitle(AnalyticsEvent $event, ?object $formOrder): ?string
    {
        if (filled($event->course_title_snapshot)) {
            return (string) $event->course_title_snapshot;
        }

        if ($formOrder !== null && filled($formOrder->product_name ?? null)) {
            return (string) $formOrder->product_name;
        }

        return null;
    }

    /**
     * Atrybucja kampanii: campaign_code z eventu, fallback FormOrder.fb_source.
     * campaign_id: z eventu, fallback fail-safe lookup po marketing_campaigns.
     *
     * @param  array<int, object>  $formOrders  (nieużywane bezpośrednio; przekazujemy gotowy $formOrder)
     * @return array{code: string, id: int|null}|null
     */
    private function resolveCampaign(AnalyticsEvent $event, ?object $formOrder): ?array
    {
        $code = trim((string) ($event->campaign_code ?? ''));
        $campaignId = $event->campaign_id !== null ? (int) $event->campaign_id : null;

        if ($code === '' && $formOrder !== null) {
            $code = trim((string) ($formOrder->fb_source ?? ''));
            $campaignId = null; // fb_source nie niesie id — spróbujemy rozwiązać niżej
        }

        if ($code === '') {
            return null;
        }

        if ($campaignId === null) {
            $campaignId = $this->resolveCampaignId($code);
        }

        return ['code' => $code, 'id' => $campaignId];
    }

    private function resolveCampaignId(string $code): ?int
    {
        try {
            $id = MarketingCampaign::query()->where('campaign_code', $code)->value('id');

            return $id !== null ? (int) $id : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function extractAmountGross(array $metadata): float
    {
        $amount = $metadata['amount_gross'] ?? null;

        return is_numeric($amount) ? (float) $amount : 0.0;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, int|float>
     */
    private function emptyContribution(): array
    {
        return [
            'orders_created' => 0,
            'ordered_revenue_gross' => 0.0,
            'online_paid_orders' => 0,
            'online_paid_revenue_gross' => 0.0,
            'deferred_invoiced_orders' => 0,
            'deferred_invoiced_revenue_gross' => 0.0,
            'online_invoiced_marker_orders' => 0,
        ];
    }

    /**
     * @return list<string>
     */
    private function revenueEventNames(): array
    {
        return [
            AnalyticsEventName::FormOrderCreated->value,
            AnalyticsEventName::PaymentStatusChanged->value,
            AnalyticsEventName::InvoiceCreated->value,
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
