<?php

namespace App\Services\Analytics;

use App\Enums\Analytics\AnalyticsEventName;
use App\Models\Analytics\AnalyticsDailyCampaignStat;
use App\Models\Analytics\AnalyticsDailyCourseStat;
use App\Models\Analytics\AnalyticsEvent;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AnalyticsDailyAggregationService
{
    /**
     * @return array{course_rows: int, campaign_rows: int, dates: list<string>}
     */
    public function aggregateForDate(Carbon|string $statDate): array
    {
        $date = $this->normalizeStatDate($statDate);

        return [
            'course_rows' => $this->aggregateCourseStatsForDate($date),
            'campaign_rows' => $this->aggregateCampaignStatsForDate($date),
            'dates' => [$date->toDateString()],
        ];
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
            $normalized = $this->normalizeStatDate($date);
            $courseRows += $this->aggregateCourseStatsForDate($normalized);
            $campaignRows += $this->aggregateCampaignStatsForDate($normalized);
            $dates[] = $normalized->toDateString();
        }

        return [
            'course_rows' => $courseRows,
            'campaign_rows' => $campaignRows,
            'dates' => $dates,
        ];
    }

    public function defaultStatDate(): Carbon
    {
        return Carbon::now($this->timezone())->subDay()->startOfDay();
    }

    public function timezone(): string
    {
        return (string) config('analytics.aggregation.timezone', 'Europe/Warsaw');
    }

    private function aggregateCourseStatsForDate(Carbon $statDate): int
    {
        $dateString = $statDate->toDateString();
        [$rangeStart, $rangeEnd] = $this->dayBoundsInUtc($statDate);

        AnalyticsDailyCourseStat::query()
            ->whereDate('stat_date', $dateString)
            ->delete();

        $events = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [$rangeStart, $rangeEnd])
            ->whereNotNull('course_id')
            ->get();

        $aggregates = [];

        foreach ($events as $event) {
            $courseId = (int) $event->course_id;

            if ($courseId <= 0) {
                continue;
            }

            if (! isset($aggregates[$courseId])) {
                $aggregates[$courseId] = $this->emptyCourseAggregate($event->course_title_snapshot);
            }

            if (filled($event->course_title_snapshot)) {
                $aggregates[$courseId]['course_title_snapshot'] = $event->course_title_snapshot;
            }

            $this->incrementCourseMetric($aggregates[$courseId], $event);
        }

        $now = now();

        foreach ($aggregates as $courseId => $row) {
            AnalyticsDailyCourseStat::query()->create(array_merge($row, [
                'stat_date' => $dateString,
                'course_id' => $courseId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        return count($aggregates);
    }

    private function aggregateCampaignStatsForDate(Carbon $statDate): int
    {
        $dateString = $statDate->toDateString();
        [$rangeStart, $rangeEnd] = $this->dayBoundsInUtc($statDate);

        AnalyticsDailyCampaignStat::query()
            ->whereDate('stat_date', $dateString)
            ->whereNull('landing_target')
            ->whereNull('campaign_content_depth')
            ->whereNull('cta_type')
            ->delete();

        $events = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [$rangeStart, $rangeEnd])
            ->whereNotNull('campaign_code')
            ->where('campaign_code', '!=', '')
            ->get();

        $aggregates = [];

        foreach ($events as $event) {
            $campaignCode = trim((string) $event->campaign_code);

            if ($campaignCode === '') {
                continue;
            }

            if (! isset($aggregates[$campaignCode])) {
                $aggregates[$campaignCode] = $this->emptyCampaignAggregate();
            }

            $this->trackCampaignDimensions($aggregates[$campaignCode], $event);
            $this->incrementCampaignMetric($aggregates[$campaignCode], $event);
        }

        $now = now();

        foreach ($aggregates as $campaignCode => $row) {
            $campaignId = $row['campaign_id'];
            $campaignChannel = $row['campaign_channel'];
            unset($row['campaign_id_counts'], $row['campaign_channel_counts'], $row['campaign_id'], $row['campaign_channel']);

            AnalyticsDailyCampaignStat::query()->create(array_merge($row, [
                'stat_date' => $dateString,
                'campaign_code' => $campaignCode,
                'campaign_id' => $campaignId,
                'campaign_channel' => $campaignChannel,
                'landing_target' => null,
                'campaign_content_depth' => null,
                'cta_type' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        return count($aggregates);
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

    /**
     * @return array<string, int|float|string|null>
     */
    private function emptyCourseAggregate(?string $courseTitleSnapshot): array
    {
        return [
            'course_title_snapshot' => $courseTitleSnapshot,
            'views_course_description' => 0,
            'views_order_form' => 0,
            'form_starts' => 0,
            'submit_attempts' => 0,
            'validation_failures' => 0,
            'orders_created' => 0,
            'payment_orders_created' => 0,
            'paid_orders' => 0,
            'invoiced_orders' => 0,
            'revenue_snapshot' => 0,
        ];
    }

    /**
     * @return array<string, int|float|string|null|array<string, int>>
     */
    private function emptyCampaignAggregate(): array
    {
        return [
            'campaign_id' => null,
            'campaign_channel' => null,
            'campaign_channel_counts' => [],
            'campaign_id_counts' => [],
            'link_entries' => 0,
            'course_description_views' => 0,
            'order_form_views' => 0,
            'form_starts' => 0,
            'submit_attempts' => 0,
            'validation_failures' => 0,
            'orders_created' => 0,
            'paid_orders' => 0,
            'invoiced_orders' => 0,
            'revenue_snapshot' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $aggregate
     */
    private function incrementCourseMetric(array &$aggregate, AnalyticsEvent $event): void
    {
        match ($event->event_name) {
            AnalyticsEventName::CourseDescriptionViewed->value => $aggregate['views_course_description']++,
            AnalyticsEventName::OrderFormViewed->value => $aggregate['views_order_form']++,
            AnalyticsEventName::OrderFormSubmitAttempted->value => $aggregate['submit_attempts']++,
            AnalyticsEventName::OrderFormValidationFailed->value => $aggregate['validation_failures']++,
            AnalyticsEventName::FormOrderCreated->value => $aggregate['orders_created']++,
            default => null,
        };

        if ($event->event_name === AnalyticsEventName::FormOrderCreated->value) {
            $aggregate['revenue_snapshot'] += $this->extractAmountGross($event);
        }
    }

    /**
     * @param  array<string, mixed>  $aggregate
     */
    private function incrementCampaignMetric(array &$aggregate, AnalyticsEvent $event): void
    {
        match ($event->event_name) {
            AnalyticsEventName::CampaignShortLinkVisit->value => $aggregate['link_entries']++,
            AnalyticsEventName::CourseDescriptionViewed->value => $aggregate['course_description_views']++,
            AnalyticsEventName::OrderFormViewed->value => $aggregate['order_form_views']++,
            AnalyticsEventName::OrderFormSubmitAttempted->value => $aggregate['submit_attempts']++,
            AnalyticsEventName::OrderFormValidationFailed->value => $aggregate['validation_failures']++,
            AnalyticsEventName::FormOrderCreated->value => $aggregate['orders_created']++,
            default => null,
        };

        if ($event->event_name === AnalyticsEventName::FormOrderCreated->value) {
            $aggregate['revenue_snapshot'] += $this->extractAmountGross($event);
        }
    }

    /**
     * @param  array<string, mixed>  $aggregate
     */
    private function trackCampaignDimensions(array &$aggregate, AnalyticsEvent $event): void
    {
        if ($event->campaign_id !== null) {
            $key = (string) $event->campaign_id;
            $aggregate['campaign_id_counts'][$key] = ($aggregate['campaign_id_counts'][$key] ?? 0) + 1;
        }

        if (filled($event->campaign_channel)) {
            $key = (string) $event->campaign_channel;
            $aggregate['campaign_channel_counts'][$key] = ($aggregate['campaign_channel_counts'][$key] ?? 0) + 1;
        }

        $aggregate['campaign_id'] = $this->dominantKey($aggregate['campaign_id_counts']);
        $aggregate['campaign_channel'] = $this->dominantKey($aggregate['campaign_channel_counts']);
    }

    private function extractAmountGross(AnalyticsEvent $event): float
    {
        $metadata = is_array($event->metadata) ? $event->metadata : [];
        $amount = $metadata['amount_gross'] ?? null;

        if (! is_numeric($amount)) {
            return 0.0;
        }

        return (float) $amount;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function dominantKey(array $counts): mixed
    {
        if ($counts === []) {
            return null;
        }

        arsort($counts);

        $topKey = array_key_first($counts);

        return is_numeric($topKey) ? (int) $topKey : $topKey;
    }
}
