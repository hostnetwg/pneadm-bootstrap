<?php

namespace App\Services\Analytics;

use App\Enums\Analytics\AnalyticsEventName;
use App\Models\Analytics\AnalyticsDailyCampaignFunnel;
use App\Models\Analytics\AnalyticsDailyChannelFunnel;
use App\Models\Analytics\AnalyticsDailyCourseChannelFunnel;
use App\Models\Analytics\AnalyticsDailyDataQuality;
use App\Models\Analytics\AnalyticsDailyGusChannelFunnel;
use App\Models\Analytics\AnalyticsEvent;
use App\Models\Analytics\OrderFormAttribution;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class OrderFormFunnelAggregationService
{
    private ?Carbon $currentStatDate = null;

    /** @var list<string> */
    private const V2_EVENT_NAMES = [
        'form_visible',
        'form_first_interaction',
        'form_section_viewed',
        'form_section_started',
        'form_section_completed',
        'form_field_changed',
        'form_submit_clicked',
        'client_validation_failed',
        'form_last_activity',
    ];

    /** @var list<string> */
    private const FRONTEND_EVENT_NAMES = [
        'form_visible',
        'form_first_interaction',
        'form_section_viewed',
        'form_section_started',
        'form_section_completed',
        'form_field_changed',
        'form_submit_clicked',
        'client_validation_failed',
        'form_last_activity',
        'gus_lookup_clicked',
        'gus_data_applied',
        'form_field_edited_after_gus',
        'gus_manual_fallback_started',
        'order_form_started',
        'order_form_section_interacted',
        'order_form_cta_clicked',
        'order_form_submit_clicked',
    ];

    public function __construct(
        private readonly OrderFormFunnelSubmitOutcomeClassifier $submitOutcomeClassifier,
        private readonly OrderFormFunnelDataQualityEvaluator $dataQualityEvaluator,
    ) {}

    /**
     * @return array{
     *     channel_rows: int,
     *     course_channel_rows: int,
     *     campaign_rows: int,
     *     gus_rows: int,
     *     data_quality_rows: int,
     *     dates: list<string>
     * }
     */
    public function aggregateForDate(Carbon|string $statDate): array
    {
        return $this->aggregate($this->normalizeStatDate($statDate));
    }

    /**
     * @return array{
     *     channel_rows: int,
     *     course_channel_rows: int,
     *     campaign_rows: int,
     *     gus_rows: int,
     *     data_quality_rows: int,
     *     dates: list<string>
     * }
     */
    public function aggregateForDateRange(Carbon|string $from, Carbon|string $to): array
    {
        $start = $this->normalizeStatDate($from);
        $end = $this->normalizeStatDate($to);

        if ($start->greaterThan($end)) {
            throw new \InvalidArgumentException('Data początkowa nie może być późniejsza niż data końcowa.');
        }

        $totals = [
            'channel_rows' => 0,
            'course_channel_rows' => 0,
            'campaign_rows' => 0,
            'gus_rows' => 0,
            'data_quality_rows' => 0,
            'dates' => [],
        ];

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $result = $this->aggregate($this->normalizeStatDate($date));
            foreach (['channel_rows', 'course_channel_rows', 'campaign_rows', 'gus_rows', 'data_quality_rows'] as $key) {
                $totals[$key] += $result[$key];
            }
            $totals['dates'][] = $result['dates'][0];
        }

        return $totals;
    }

    public function defaultStatDate(): Carbon
    {
        $lag = max(1, (int) config('analytics.order_form_funnel.aggregation_lag_days', 2));

        return Carbon::now($this->timezone())->subDays($lag)->startOfDay();
    }

    public function timezone(): string
    {
        return (string) config(
            'analytics.order_form_funnel.timezone',
            config('analytics.aggregation.timezone', 'Europe/Warsaw')
        );
    }

    public function aggregationLagDays(): int
    {
        return max(1, (int) config('analytics.order_form_funnel.aggregation_lag_days', 2));
    }

    /**
     * @return array{
     *     channel_rows: int,
     *     course_channel_rows: int,
     *     campaign_rows: int,
     *     gus_rows: int,
     *     data_quality_rows: int,
     *     dates: list<string>
     * }
     */
    private function aggregate(Carbon $statDate): array
    {
        $dateString = $statDate->toDateString();
        $this->currentStatDate = $statDate->copy()->startOfDay();
        [$rangeStart, $rangeEnd] = $this->dayBoundsInUtc($statDate);

        $this->deleteExistingRows($dateString);

        $sessionIds = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [$rangeStart, $rangeEnd])
            ->whereNotNull('order_form_session_id')
            ->whereIn('event_name', $this->trackedEventNames())
            ->distinct()
            ->pluck('order_form_session_id')
            ->all();

        if ($sessionIds === []) {
            $now = now();
            AnalyticsDailyDataQuality::query()->create(array_merge(
                $this->finalizeDataQuality($this->emptyDataQuality(), $dateString),
                [
                    'stat_date' => $dateString,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            ));

            return [
                'channel_rows' => 0,
                'course_channel_rows' => 0,
                'campaign_rows' => 0,
                'gus_rows' => 0,
                'data_quality_rows' => 1,
                'dates' => [$dateString],
            ];
        }

        $channelAggregates = [];
        $courseChannelAggregates = [];
        $campaignAggregates = [];
        $gusAggregates = [];
        $dataQuality = $this->emptyDataQuality();

        foreach (array_chunk($sessionIds, 250) as $chunk) {
            $attributions = OrderFormAttribution::query()
                ->whereIn('form_session_id', $chunk)
                ->get()
                ->keyBy('form_session_id');

            $events = AnalyticsEvent::query()
                ->whereIn('order_form_session_id', $chunk)
                ->whereIn('event_name', $this->trackedEventNames())
                ->get();

            foreach ($events->groupBy('order_form_session_id') as $sessionId => $sessionEvents) {
                $session = $this->summarizeSession(
                    (string) $sessionId,
                    $sessionEvents,
                    $attributions->get((string) $sessionId),
                );

                if ($session['first_event_date'] !== $dateString) {
                    continue;
                }

                $this->applySessionToChannelFunnels($channelAggregates, $session);
                if ($session['course_id'] !== null) {
                    $this->applySessionToCourseChannelFunnels($courseChannelAggregates, $session);
                }
                $this->applySessionToCampaignFunnels($campaignAggregates, $session);
                $this->applySessionToGusFunnels($gusAggregates, $session);
                $this->applySessionToDataQuality($dataQuality, $session);
            }
        }

        $now = now();
        $channelRows = $this->persistRows(AnalyticsDailyChannelFunnel::class, $channelAggregates, $dateString, $now);
        $courseRows = $this->persistRows(AnalyticsDailyCourseChannelFunnel::class, $courseChannelAggregates, $dateString, $now);
        $campaignRows = $this->persistRows(AnalyticsDailyCampaignFunnel::class, $campaignAggregates, $dateString, $now);
        $gusRows = $this->persistGusRows($gusAggregates, $dateString, $now);
        $qualityRow = $this->finalizeDataQuality($dataQuality, $dateString);
        AnalyticsDailyDataQuality::query()->create(array_merge($qualityRow, [
            'stat_date' => $dateString,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        return [
            'channel_rows' => $channelRows,
            'course_channel_rows' => $courseRows,
            'campaign_rows' => $campaignRows,
            'gus_rows' => $gusRows,
            'data_quality_rows' => 1,
            'dates' => [$dateString],
        ];
    }

    private function deleteExistingRows(string $dateString): void
    {
        AnalyticsDailyChannelFunnel::query()->whereDate('stat_date', $dateString)->delete();
        AnalyticsDailyCourseChannelFunnel::query()->whereDate('stat_date', $dateString)->delete();
        AnalyticsDailyCampaignFunnel::query()->whereDate('stat_date', $dateString)->delete();
        AnalyticsDailyGusChannelFunnel::query()->whereDate('stat_date', $dateString)->delete();
        AnalyticsDailyDataQuality::query()->whereDate('stat_date', $dateString)->delete();
    }

    /**
     * @param  Collection<int, AnalyticsEvent>  $sessionEvents
     * @return array<string, mixed>
     */
    private function summarizeSession(string $sessionId, Collection $sessionEvents, ?OrderFormAttribution $attribution): array
    {
        $firstEventAt = null;
        $courseId = null;
        $courseTitle = null;
        $priceVariantId = null;
        $campaignFirstAt = null;
        $campaignCode = null;
        $campaignId = null;

        $flags = [
            'session_entry' => false,
            'form_visible' => false,
            'first_interaction' => false,
            'reached_started' => false,
            'reached_submit_clicked' => false,
            'client_validation_failed' => false,
            'server_submit_attempted' => false,
            'server_validation_failed' => false,
            'order_create_failed' => false,
            'order_created' => false,
            'has_frontend_events' => false,
            'has_schema_v2_events' => false,
            'payment_deferred_selected' => false,
            'payment_online_selected' => false,
        ];

        $submitClickedAt = null;
        $lastEventAt = null;

        $gus = [
            'buyer' => $this->emptyGusTargetState(),
            'recipient' => $this->emptyGusTargetState(),
            'any_lookup' => false,
            'any_success' => false,
            'any_error' => false,
            'data_applied' => false,
            'edited_after_gus' => false,
            'manual_fallback' => false,
            'error_then_success' => false,
            'recovered_after_error' => false,
            'latency_ms_sum' => 0,
            'latency_ms_count' => 0,
        ];

        foreach ($sessionEvents as $event) {
            $occurredAt = Carbon::parse((string) $event->getRawOriginal('occurred_at'), 'UTC');
            $eventName = (string) $event->event_name;
            $metadata = is_array($event->metadata) ? $event->metadata : [];

            if ($firstEventAt === null || $occurredAt->lessThan($firstEventAt)) {
                $firstEventAt = $occurredAt;
            }

            if ($lastEventAt === null || $occurredAt->greaterThan($lastEventAt)) {
                $lastEventAt = $occurredAt;
            }

            if (in_array($eventName, self::V2_EVENT_NAMES, true)
                || (int) ($metadata['tracking_schema_version'] ?? 0) === 2) {
                $flags['has_schema_v2_events'] = true;
            }

            if ($courseId === null && $event->course_id !== null && (int) $event->course_id > 0) {
                $courseId = (int) $event->course_id;
            }

            if ($courseTitle === null && filled($event->course_title_snapshot)) {
                $courseTitle = $event->course_title_snapshot;
            }

            if ($priceVariantId === null && isset($metadata['price_variant_id']) && is_numeric($metadata['price_variant_id'])) {
                $priceVariantId = (int) $metadata['price_variant_id'];
            }

            $code = trim((string) ($event->campaign_code ?? $metadata['campaign_code'] ?? $event->utm_campaign ?? ''));
            if ($code !== '' && ($campaignFirstAt === null || $occurredAt->lessThan($campaignFirstAt))) {
                $campaignFirstAt = $occurredAt;
                $campaignCode = $code;
                $campaignId = $event->campaign_id !== null ? (int) $event->campaign_id : null;
            }

            $this->applyEventToFlags($flags, $gus, $eventName, $metadata, $occurredAt, $submitClickedAt);
        }

        $trafficChannel = $attribution?->traffic_channel
            ?: $attribution?->conversion_reporting_channel
            ?: 'unknown';
        $conversionChannel = $attribution?->conversion_reporting_channel ?: $trafficChannel;

        $hasAttribution = $attribution !== null;
        $hasKnownTrafficChannel = $hasAttribution && filled($attribution->traffic_channel) && $attribution->traffic_channel !== 'unknown';

        $serverOnly = $flags['order_created'] && ! $flags['has_frontend_events'];
        $frontendOnlyAbandonment = $flags['has_frontend_events']
            && ! $flags['server_submit_attempted']
            && ! $flags['order_created'];

        $firstEventAt ??= now();

        return [
            'form_session_id' => $sessionId,
            'first_event_date' => $firstEventAt->copy()->timezone($this->timezone())->toDateString(),
            'course_id' => $courseId,
            'price_variant_id' => $priceVariantId,
            'course_title_snapshot' => $courseTitle,
            'traffic_channel' => $trafficChannel,
            'traffic_source' => $attribution?->traffic_source,
            'traffic_medium' => $attribution?->traffic_medium,
            'traffic_campaign' => $attribution?->traffic_campaign,
            'conversion_reporting_channel' => $conversionChannel,
            'tracking_schema_version' => (int) ($attribution?->tracking_schema_version ?? 2),
            'campaign_code' => $campaignCode,
            'campaign_id' => $campaignId,
            'internal_promo_touched' => (bool) ($attribution?->internal_promo_touched ?? false),
            'internal_promo_placement' => $attribution?->internal_promo_placement,
            'internal_promo_context' => $attribution?->internal_promo_context,
            'has_attribution' => $hasAttribution,
            'has_known_traffic_channel' => $hasKnownTrafficChannel,
            'has_campaign' => $campaignCode !== null,
            'flags' => $flags,
            'gus' => $gus,
            'submit_clicked_at' => $submitClickedAt,
            'last_event_at' => $lastEventAt ?? $firstEventAt,
            'server_only_conversion' => $serverOnly,
            'frontend_only_abandonment' => $frontendOnlyAbandonment,
            'full_funnel_order' => $flags['order_created'] && $flags['has_frontend_events'] && $flags['server_submit_attempted'],
        ];
    }

    /**
     * @param  array<string, bool>  $flags
     * @param  array<string, mixed>  $gus
     * @param  array<string, mixed>  $metadata
     */
    private function applyEventToFlags(
        array &$flags,
        array &$gus,
        string $eventName,
        array $metadata,
        Carbon $occurredAt,
        ?Carbon &$submitClickedAt,
    ): void {
        if (in_array($eventName, self::FRONTEND_EVENT_NAMES, true)) {
            $flags['has_frontend_events'] = true;
        }

        match ($eventName) {
            AnalyticsEventName::OrderFormViewed->value => $flags['session_entry'] = true,
            AnalyticsEventName::FormFirstInteraction->value,
            'order_form_started' => $this->markStarted($flags),
            AnalyticsEventName::FormSubmitClicked->value,
            AnalyticsEventName::OrderFormSubmitClicked->value => $this->markSubmitClicked($flags, $occurredAt, $submitClickedAt),
            AnalyticsEventName::ClientValidationFailed->value => $flags['client_validation_failed'] = true,
            AnalyticsEventName::OrderFormSubmitAttempted->value => $flags['server_submit_attempted'] = true,
            AnalyticsEventName::OrderFormValidationFailed->value => $flags['server_validation_failed'] = true,
            AnalyticsEventName::OrderCreateFailed->value => $flags['order_create_failed'] = true,
            AnalyticsEventName::FormOrderCreated->value => $flags['order_created'] = true,
            AnalyticsEventName::DeferredInvoiceSelected->value => $flags['payment_deferred_selected'] = true,
            AnalyticsEventName::OnlinePaymentSelected->value => $flags['payment_online_selected'] = true,
            AnalyticsEventName::GusLookupClicked->value => $this->markGusEvent($gus, 'clicked', $metadata, $occurredAt),
            AnalyticsEventName::GusLookupStarted->value => $this->markGusEvent($gus, 'started', $metadata, $occurredAt),
            AnalyticsEventName::GusLookupSuccess->value => $this->markGusEvent($gus, 'success', $metadata, $occurredAt),
            AnalyticsEventName::GusLookupError->value => $this->markGusEvent($gus, 'error', $metadata, $occurredAt),
            AnalyticsEventName::GusDataApplied->value => $gus['data_applied'] = true,
            AnalyticsEventName::FormFieldEditedAfterGus->value => $gus['edited_after_gus'] = true,
            AnalyticsEventName::GusManualFallbackStarted->value => $gus['manual_fallback'] = true,
            default => null,
        };

        if ($eventName === AnalyticsEventName::FormVisible->value) {
            $flags['form_visible'] = true;
            $flags['session_entry'] = true;
        }
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function markStarted(array &$flags): void
    {
        $flags['first_interaction'] = true;
        $flags['reached_started'] = true;
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function markSubmitClicked(array &$flags, Carbon $occurredAt, ?Carbon &$submitClickedAt): void
    {
        $flags['reached_submit_clicked'] = true;

        if ($submitClickedAt === null || $occurredAt->lessThan($submitClickedAt)) {
            $submitClickedAt = $occurredAt;
        }
    }

    /**
     * @param  array<string, mixed>  $gus
     * @param  array<string, mixed>  $metadata
     */
    private function markGusEvent(array &$gus, string $type, array $metadata, Carbon $occurredAt): void
    {
        $target = $this->normalizeGusTarget($metadata['target'] ?? $metadata['gus_target'] ?? null);
        $gus['any_lookup'] = $gus['any_lookup'] || in_array($type, ['clicked', 'started'], true);

        if ($type === 'success') {
            $gus['any_success'] = true;
            if (isset($metadata['latency_ms']) && is_numeric($metadata['latency_ms'])) {
                $gus['latency_ms_sum'] += (int) $metadata['latency_ms'];
                $gus['latency_ms_count']++;
            }
        }

        if ($type === 'error') {
            $gus['any_error'] = true;
        }

        $state = &$gus[$target];
        $state[$type] = true;

        if ($type === 'error') {
            $state['last_error_at'] = $occurredAt;
        }

        if ($type === 'success' && $state['last_error_at'] instanceof Carbon) {
            $gus['error_then_success'] = true;
            if ($state['last_error_at']->lessThan($occurredAt)) {
                $state['recovered'] = true;
                $gus['recovered_after_error'] = true;
            }
        }
    }

  /**
     * @return array<string, mixed>
     */
    private function emptyGusTargetState(): array
    {
        return [
            'clicked' => false,
            'started' => false,
            'success' => false,
            'error' => false,
            'recovered' => false,
            'last_error_at' => null,
        ];
    }

    private function normalizeGusTarget(mixed $target): string
    {
        $target = is_string($target) ? strtolower(trim($target)) : '';

        return in_array($target, ['buyer', 'recipient'], true) ? $target : 'buyer';
    }

    /**
     * @param  array<string, array<string, mixed>>  $aggregates
     * @param  array<string, mixed>  $session
     */
    private function applySessionToChannelFunnels(array &$aggregates, array $session): void
    {
        $key = $this->channelDimensionKey($session);
        $this->ensureFunnelRow($aggregates, $key, $this->channelIdentity($session));
        $this->incrementFunnelMetrics($aggregates[$key], $session);
    }

    /**
     * @param  array<string, array<string, mixed>>  $aggregates
     * @param  array<string, mixed>  $session
     */
    private function applySessionToCourseChannelFunnels(array &$aggregates, array $session): void
    {
        $key = $this->courseChannelDimensionKey($session);
        $this->ensureFunnelRow($aggregates, $key, $this->courseChannelIdentity($session));
        $this->incrementFunnelMetrics($aggregates[$key], $session);
    }

    /**
     * @param  array<string, array<string, mixed>>  $aggregates
     * @param  array<string, mixed>  $session
     */
    private function applySessionToCampaignFunnels(array &$aggregates, array $session): void
    {
        $key = $this->campaignDimensionKey($session);
        if (! isset($aggregates[$key])) {
            $aggregates[$key] = array_merge($this->campaignIdentity($session), [
                'sessions_total' => 0,
                'order_created' => 0,
                'first_interaction' => 0,
                'reached_submit_clicked' => 0,
                'server_submit_attempted' => 0,
                'server_validation_failed' => 0,
                'abandonment_before_first_interaction' => 0,
                'gus_success_sessions' => 0,
                'gus_error_sessions' => 0,
                'server_only_conversions' => 0,
                'sessions_without_campaign_metadata' => 0,
                'suspicious_campaign_name_count' => 0,
                'campaign_course_mismatch_count' => 0,
            ]);
        }

        $row = &$aggregates[$key];
        $flags = $session['flags'];
        $row['sessions_total']++;

        if ($flags['order_created']) {
            $row['order_created']++;
        }
        if ($flags['first_interaction']) {
            $row['first_interaction']++;
        }
        if ($flags['reached_submit_clicked']) {
            $row['reached_submit_clicked']++;
        }
        if ($flags['server_submit_attempted']) {
            $row['server_submit_attempted']++;
        }
        if ($flags['server_validation_failed']) {
            $row['server_validation_failed']++;
        }
        if (! $session['has_campaign']) {
            $row['sessions_without_campaign_metadata']++;
        }
        if ($session['gus']['any_success']) {
            $row['gus_success_sessions']++;
        }
        if ($session['gus']['any_error']) {
            $row['gus_error_sessions']++;
        }
        if ($session['server_only_conversion']) {
            $row['server_only_conversions']++;
        }
        if (! $flags['first_interaction'] && ! $flags['order_created']) {
            $row['abandonment_before_first_interaction']++;
        }

        $row['conversion_rate'] = $this->rate($row['order_created'], $row['sessions_total']);
    }

    /**
     * @param  array<string, array<string, mixed>>  $aggregates
     * @param  array<string, mixed>  $session
     */
    private function applySessionToGusFunnels(array &$aggregates, array $session): void
    {
        foreach (['buyer', 'recipient', 'all'] as $target) {
            $key = $this->gusDimensionKey($session, $target);
            if (! isset($aggregates[$key])) {
                $aggregates[$key] = $this->emptyGusAggregate($session, $target);
            }

            $this->incrementGusMetrics($aggregates[$key], $session, $target);
        }
    }

    /**
     * @param  array<string, int|float>  $dataQuality
     * @param  array<string, mixed>  $session
     */
    private function applySessionToDataQuality(array &$dataQuality, array $session): void
    {
        $flags = $session['flags'];
        $dataQuality['sessions_total']++;

        if ($flags['has_frontend_events']) {
            $dataQuality['sessions_with_frontend_events']++;
        } else {
            $dataQuality['sessions_backend_only']++;
        }

        if ($session['has_attribution']) {
            $dataQuality['sessions_with_attribution']++;
        } else {
            $dataQuality['sessions_without_attribution']++;
        }

        if ($session['has_known_traffic_channel']) {
            $dataQuality['sessions_with_traffic_channel']++;
        } else {
            $dataQuality['sessions_without_traffic_channel']++;
        }

        if ($session['has_campaign']) {
            $dataQuality['sessions_with_campaign']++;
        } else {
            $dataQuality['sessions_without_campaign']++;
        }

        if ($flags['order_created']) {
            $dataQuality['orders_total']++;
            if ($session['full_funnel_order']) {
                $dataQuality['orders_with_full_funnel']++;
            }
            if ($session['server_only_conversion']) {
                $dataQuality['orders_backend_only']++;
                $dataQuality['server_only_conversions']++;
            }
            if ($session['has_attribution']) {
                $dataQuality['orders_with_attribution']++;
            } else {
                $dataQuality['orders_without_attribution']++;
            }
        }

        if ($flags['has_schema_v2_events']) {
            $dataQuality['sessions_with_schema_v2_events']++;
        }

        $version = (int) ($session['tracking_schema_version'] ?? 2);
        if (! isset($dataQuality['_schema_versions_seen']) || ! is_array($dataQuality['_schema_versions_seen'])) {
            $dataQuality['_schema_versions_seen'] = [];
        }
        if (! in_array($version, $dataQuality['_schema_versions_seen'], true)) {
            $dataQuality['_schema_versions_seen'][] = $version;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $aggregates
     * @param  array<string, mixed>  $identity
     */
    private function ensureFunnelRow(array &$aggregates, string $key, array $identity): void
    {
        if (! isset($aggregates[$key])) {
            $aggregates[$key] = array_merge($identity, $this->emptyFunnelMetrics());
        }

        foreach ($identity as $field => $value) {
            if ($value !== null && $value !== '') {
                $aggregates[$key][$field] = $value;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $session
     */
    private function incrementFunnelMetrics(array &$row, array $session): void
    {
        $flags = $session['flags'];
        $gus = $session['gus'];

        if (! $flags['session_entry'] && ! $flags['form_visible']) {
            return;
        }

        $row['sessions_total']++;

        foreach ([
            'form_visible' => 'form_visible',
            'first_interaction' => 'first_interaction',
            'reached_started' => 'reached_started',
            'reached_submit_clicked' => 'reached_submit_clicked',
            'server_submit_attempted' => 'server_submit_attempted',
            'server_validation_failed' => 'server_validation_failed',
            'order_create_failed' => 'order_create_failed',
            'order_created' => 'order_created',
            'payment_deferred_selected' => 'payment_deferred_selected',
            'payment_online_selected' => 'payment_online_selected',
        ] as $flag => $column) {
            if ($flags[$flag]) {
                $row[$column]++;
            }
        }

        if ($gus['buyer']['clicked']) {
            $row['gus_buyer_lookup_clicked']++;
        }
        if ($gus['buyer']['success']) {
            $row['gus_buyer_success']++;
        }
        if ($gus['buyer']['error']) {
            $row['gus_buyer_error']++;
        }
        if ($gus['recipient']['clicked']) {
            $row['gus_recipient_lookup_clicked']++;
        }
        if ($gus['recipient']['success']) {
            $row['gus_recipient_success']++;
        }
        if ($gus['recipient']['error']) {
            $row['gus_recipient_error']++;
        }
        if ($gus['any_success']) {
            $row['gus_success_sessions']++;
        }
        if ($gus['any_error']) {
            $row['gus_error_sessions']++;
        }
        if ($gus['data_applied']) {
            $row['gus_data_applied_sessions']++;
        }
        if ($gus['edited_after_gus']) {
            $row['edited_after_gus_sessions']++;
        }
        if ($gus['manual_fallback']) {
            $row['gus_manual_fallback_sessions']++;
        }

        if ($session['has_attribution']) {
            $row['sessions_with_attribution']++;
        } else {
            $row['sessions_without_attribution']++;
        }
        if ($flags['has_frontend_events']) {
            $row['sessions_with_frontend_events']++;
        } else {
            $row['sessions_backend_only']++;
        }
        if ($flags['order_created']) {
            if ($session['has_attribution']) {
                $row['orders_with_attribution']++;
            } else {
                $row['orders_without_attribution']++;
            }
        }
        if ($session['server_only_conversion']) {
            $row['server_only_conversions']++;
        }
        if ($session['frontend_only_abandonment']) {
            $row['frontend_only_abandonments']++;
        }

        if (! $flags['first_interaction'] && ! $flags['order_created']) {
            $row['abandonment_before_first_interaction']++;
        }
        if ($flags['first_interaction'] && ! $flags['reached_submit_clicked'] && ! $flags['order_created']) {
            $row['abandonment_after_first_interaction']++;
        }

        $submitOutcome = $this->currentStatDate !== null
            ? $this->submitOutcomeClassifier->classify($session, $this->currentStatDate, $this->timezone())
            : null;

        if ($submitOutcome !== null && isset($row[$submitOutcome])) {
            $row[$submitOutcome]++;
        }

        if ($submitOutcome === 'server_validation_abandonment') {
            $row['abandoned_after_server_validation_failed']++;
        }

        $row['conversion_rate'] = $this->rate($row['order_created'], $row['sessions_total']);
        $row['visible_to_first_interaction_rate'] = $this->rate($row['first_interaction'], max(1, $row['form_visible'] ?: $row['sessions_total']));
        $row['started_to_created_rate'] = $this->rate($row['order_created'], max(1, $row['reached_started']));
        $row['submit_to_created_rate'] = $this->rate($row['order_created'], max(1, $row['server_submit_attempted']));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $session
     */
    private function incrementGusMetrics(array &$row, array $session, string $target): void
    {
        $flags = $session['flags'];
        $gus = $session['gus'];
        $targetState = $target === 'all'
            ? [
                'clicked' => $gus['buyer']['clicked'] || $gus['recipient']['clicked'],
                'started' => $gus['buyer']['started'] || $gus['recipient']['started'],
                'success' => $gus['buyer']['success'] || $gus['recipient']['success'],
                'error' => $gus['buyer']['error'] || $gus['recipient']['error'],
                'recovered' => $gus['recovered_after_error'],
            ]
            : $gus[$target];

        if (! $flags['session_entry'] && ! $flags['form_visible']) {
            return;
        }

        $row['sessions_total']++;

        $hasLookup = $targetState['clicked'] || $targetState['started'];
        if ($hasLookup) {
            $row['sessions_with_gus_lookup']++;
            if ($targetState['clicked']) {
                $row['gus_lookup_clicked']++;
            }
            if ($targetState['started']) {
                $row['gus_lookup_started']++;
            }
        } else {
            $row['sessions_without_gus_lookup']++;
        }

        if ($targetState['success']) {
            $row['gus_lookup_success']++;
        }
        if ($targetState['error']) {
            $row['gus_lookup_error']++;
        }
        if ($gus['data_applied'] && ($target === 'all' || $targetState['success'])) {
            $row['gus_data_applied']++;
        }
        if ($gus['edited_after_gus'] && ($target === 'all' || $targetState['success'])) {
            $row['form_field_edited_after_gus']++;
        }
        if ($gus['manual_fallback'] && ($target === 'all' || $targetState['error'])) {
            $row['gus_manual_fallback_started']++;
        }

        if ($flags['order_created']) {
            if ($targetState['success']) {
                $row['orders_after_gus_success']++;
            } elseif ($targetState['error']) {
                $row['orders_after_gus_error']++;
            } elseif (! $hasLookup) {
                $row['orders_without_gus']++;
            }
        } elseif ($targetState['error'] && ! $targetState['recovered']) {
            $row['abandonment_after_gus_error']++;
        } elseif ($targetState['success'] && ! $flags['order_created']) {
            $row['abandonment_after_gus_success']++;
        }

        if ($gus['recovered_after_error'] && ($target === 'all' || $targetState['recovered'])) {
            $row['recovered_after_gus_error']++;
        }
        if ($gus['error_then_success'] && ($target === 'all' || $targetState['recovered'])) {
            $row['sessions_with_gus_error_then_success']++;
        }

        if ($gus['latency_ms_count'] > 0 && ($target === 'all' || $targetState['success'])) {
            $row['avg_gus_latency_ms'] = (int) round($gus['latency_ms_sum'] / $gus['latency_ms_count']);
        }

        $withGus = $row['sessions_with_gus_lookup'];
        $withoutGus = $row['sessions_without_gus_lookup'];
        $row['gus_success_rate'] = $this->rate($row['gus_lookup_success'], max(1, $withGus));
        $row['gus_error_rate'] = $this->rate($row['gus_lookup_error'], max(1, $withGus));

        $row['conversion_rate_with_gus'] = $this->conversionRateForSubset($row, true);
        $row['conversion_rate_without_gus'] = $this->conversionRateForSubset($row, false);
        $row['conversion_rate_after_gus_success'] = $this->rate($row['orders_after_gus_success'], max(1, $row['gus_lookup_success']));
        $row['conversion_rate_after_gus_error'] = $this->rate($row['orders_after_gus_error'], max(1, $row['gus_lookup_error']));
        $row['gus_conversion_delta'] = round($row['conversion_rate_with_gus'] - $row['conversion_rate_without_gus'], 4);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function conversionRateForSubset(array $row, bool $withGus): float
    {
        $orders = ($row['orders_after_gus_success'] ?? 0) + ($row['orders_after_gus_error'] ?? 0);
        $sessions = $withGus ? ($row['sessions_with_gus_lookup'] ?? 0) : ($row['sessions_without_gus_lookup'] ?? 0);

        if ($withGus) {
            return $this->rate($orders, max(1, $sessions));
        }

        return $this->rate($row['orders_without_gus'] ?? 0, max(1, $sessions));
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function persistRows(string $modelClass, array $rows, string $dateString, Carbon $now): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $modelClass::query()->create(array_merge($row, [
                'stat_date' => $dateString,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function persistGusRows(array $rows, string $dateString, Carbon $now): int
    {
        return $this->persistRows(AnalyticsDailyGusChannelFunnel::class, $rows, $dateString, $now);
    }

    /**
     * @param  array<string, int|float>  $dataQuality
     * @return array<string, mixed>
     */
    private function finalizeDataQuality(array $dataQuality, string $dateString): array
    {
        $sessions = max(0, (int) $dataQuality['sessions_total']);
        $orders = max(0, (int) $dataQuality['orders_total']);

        $dataQuality['frontend_tracking_coverage_rate'] = $this->rate($dataQuality['sessions_with_frontend_events'], max(1, $sessions));
        $dataQuality['attribution_coverage_rate'] = $this->rate($dataQuality['sessions_with_attribution'], max(1, $sessions));
        $dataQuality['traffic_channel_coverage_rate'] = $this->rate($dataQuality['sessions_with_traffic_channel'], max(1, $sessions));
        $dataQuality['campaign_coverage_rate'] = $this->rate($dataQuality['sessions_with_campaign'], max(1, $sessions));
        $dataQuality['schema_v2_event_rate'] = $this->rate($dataQuality['sessions_with_schema_v2_events'] ?? 0, max(1, $sessions));

        $evaluation = $this->dataQualityEvaluator->evaluate($dataQuality, $dateString, $this->timezone());
        $dataQuality['tracking_data_quality_status'] = $evaluation['status'];
        $dataQuality['tracking_data_quality_flags'] = $evaluation['flags'];
        $dataQuality['tracking_data_quality_score'] = $evaluation['score'];

        unset($dataQuality['_schema_versions_seen']);

        return $dataQuality;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function channelDimensionKey(array $session): string
    {
        return implode('|', [
            $session['traffic_channel'],
            $session['conversion_reporting_channel'],
            $session['traffic_source'] ?? '',
            $session['traffic_medium'] ?? '',
            $session['traffic_campaign'] ?? '',
            $session['tracking_schema_version'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function channelIdentity(array $session): array
    {
        return [
            'traffic_channel' => $session['traffic_channel'],
            'traffic_source' => $session['traffic_source'],
            'traffic_medium' => $session['traffic_medium'],
            'traffic_campaign' => $session['traffic_campaign'],
            'conversion_reporting_channel' => $session['conversion_reporting_channel'],
            'tracking_schema_version' => $session['tracking_schema_version'],
            'internal_promo_touched' => $session['internal_promo_touched'],
            'internal_promo_placement' => $session['internal_promo_placement'],
            'internal_promo_context' => $session['internal_promo_context'],
        ];
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function courseChannelDimensionKey(array $session): string
    {
        return implode('|', [
            $session['course_id'],
            $session['price_variant_id'] ?? '',
            $session['traffic_channel'],
            $session['conversion_reporting_channel'],
            $session['traffic_source'] ?? '',
            $session['traffic_medium'] ?? '',
            $session['traffic_campaign'] ?? '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function courseChannelIdentity(array $session): array
    {
        return array_merge($this->channelIdentity($session), [
            'course_id' => $session['course_id'],
            'price_variant_id' => $session['price_variant_id'],
            'course_title_snapshot' => $session['course_title_snapshot'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function campaignDimensionKey(array $session): string
    {
        return implode('|', [
            $session['campaign_code'] ?? '__unknown__',
            $session['course_id'] ?? '',
            $session['traffic_channel'],
            $session['traffic_source'] ?? '',
            $session['traffic_medium'] ?? '',
            $session['traffic_campaign'] ?? '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function campaignIdentity(array $session): array
    {
        return [
            'campaign_code' => $session['campaign_code'],
            'campaign_id' => $session['campaign_id'],
            'campaign_name' => $session['campaign_code'],
            'traffic_channel' => $session['traffic_channel'],
            'traffic_source' => $session['traffic_source'],
            'traffic_medium' => $session['traffic_medium'],
            'traffic_campaign' => $session['traffic_campaign'],
            'course_id' => $session['course_id'],
            'price_variant_id' => $session['price_variant_id'],
            'internal_promo_touched' => $session['internal_promo_touched'],
            'internal_promo_placement' => $session['internal_promo_placement'],
        ];
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function gusDimensionKey(array $session, string $target): string
    {
        return implode('|', [
            $session['course_id'] ?? '',
            $session['price_variant_id'] ?? '',
            $session['traffic_channel'],
            $target,
        ]);
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function emptyGusAggregate(array $session, string $target): array
    {
        return [
            'course_id' => $session['course_id'],
            'price_variant_id' => $session['price_variant_id'],
            'traffic_channel' => $session['traffic_channel'],
            'target' => $target,
            'sessions_total' => 0,
            'sessions_with_gus_lookup' => 0,
            'sessions_without_gus_lookup' => 0,
            'gus_lookup_clicked' => 0,
            'gus_lookup_started' => 0,
            'gus_lookup_success' => 0,
            'gus_lookup_error' => 0,
            'gus_success_rate' => 0,
            'gus_error_rate' => 0,
            'gus_data_applied' => 0,
            'form_field_edited_after_gus' => 0,
            'gus_manual_fallback_started' => 0,
            'orders_after_gus_success' => 0,
            'orders_after_gus_error' => 0,
            'orders_without_gus' => 0,
            'conversion_rate_with_gus' => 0,
            'conversion_rate_without_gus' => 0,
            'conversion_rate_after_gus_success' => 0,
            'conversion_rate_after_gus_error' => 0,
            'gus_conversion_delta' => 0,
            'abandonment_after_gus_success' => 0,
            'abandonment_after_gus_error' => 0,
            'recovered_after_gus_error' => 0,
            'sessions_with_gus_error_then_success' => 0,
            'avg_gus_latency_ms' => null,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function emptyFunnelMetrics(): array
    {
        return [
            'sessions_total' => 0,
            'form_visible' => 0,
            'first_interaction' => 0,
            'reached_started' => 0,
            'reached_submit_clicked' => 0,
            'server_submit_attempted' => 0,
            'server_validation_failed' => 0,
            'order_create_failed' => 0,
            'order_created' => 0,
            'conversion_rate' => 0,
            'visible_to_first_interaction_rate' => 0,
            'started_to_created_rate' => 0,
            'submit_to_created_rate' => 0,
            'abandonment_before_first_interaction' => 0,
            'abandonment_after_first_interaction' => 0,
            'abandoned_after_submit_clicked' => 0,
            'pending_after_submit_clicked' => 0,
            'validation_abandonment' => 0,
            'server_validation_abandonment' => 0,
            'backend_result_missing' => 0,
            'abandoned_after_server_validation_failed' => 0,
            'server_only_conversions' => 0,
            'frontend_only_abandonments' => 0,
            'gus_buyer_lookup_clicked' => 0,
            'gus_buyer_success' => 0,
            'gus_buyer_error' => 0,
            'gus_recipient_lookup_clicked' => 0,
            'gus_recipient_success' => 0,
            'gus_recipient_error' => 0,
            'gus_success_sessions' => 0,
            'gus_error_sessions' => 0,
            'gus_data_applied_sessions' => 0,
            'edited_after_gus_sessions' => 0,
            'gus_manual_fallback_sessions' => 0,
            'payment_deferred_selected' => 0,
            'payment_online_selected' => 0,
            'sessions_with_attribution' => 0,
            'sessions_without_attribution' => 0,
            'sessions_with_frontend_events' => 0,
            'sessions_backend_only' => 0,
            'orders_with_attribution' => 0,
            'orders_without_attribution' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyDataQuality(): array
    {
        return [
            'sessions_total' => 0,
            'sessions_with_frontend_events' => 0,
            'sessions_backend_only' => 0,
            'sessions_with_attribution' => 0,
            'sessions_without_attribution' => 0,
            'sessions_with_traffic_channel' => 0,
            'sessions_without_traffic_channel' => 0,
            'sessions_with_campaign' => 0,
            'sessions_without_campaign' => 0,
            'sessions_with_schema_v2_events' => 0,
            'orders_total' => 0,
            'orders_with_full_funnel' => 0,
            'orders_backend_only' => 0,
            'orders_with_attribution' => 0,
            'orders_without_attribution' => 0,
            'server_only_conversions' => 0,
        ];
    }

    /**
     * @return list<string>
     */
    private function trackedEventNames(): array
    {
        return [
            AnalyticsEventName::OrderFormViewed->value,
            AnalyticsEventName::FormVisible->value,
            AnalyticsEventName::FormFirstInteraction->value,
            'order_form_started',
            AnalyticsEventName::FormSubmitClicked->value,
            AnalyticsEventName::OrderFormSubmitClicked->value,
            AnalyticsEventName::OrderFormSubmitAttempted->value,
            AnalyticsEventName::OrderFormValidationFailed->value,
            AnalyticsEventName::OrderCreateFailed->value,
            AnalyticsEventName::FormOrderCreated->value,
            AnalyticsEventName::DeferredInvoiceSelected->value,
            AnalyticsEventName::OnlinePaymentSelected->value,
            AnalyticsEventName::GusLookupClicked->value,
            AnalyticsEventName::GusLookupStarted->value,
            AnalyticsEventName::GusLookupSuccess->value,
            AnalyticsEventName::GusLookupError->value,
            AnalyticsEventName::GusDataApplied->value,
            AnalyticsEventName::FormFieldEditedAfterGus->value,
            AnalyticsEventName::GusManualFallbackStarted->value,
            ...self::FRONTEND_EVENT_NAMES,
        ];
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round($numerator / $denominator, 4);
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
