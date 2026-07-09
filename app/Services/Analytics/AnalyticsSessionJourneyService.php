<?php

namespace App\Services\Analytics;

use App\Models\Analytics\AnalyticsEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Buduje etykiety podstron i chronologiczną ścieżkę nawigacji w ramach analytics_session_id.
 */
class AnalyticsSessionJourneyService
{
    /**
     * @param  Collection<int, AnalyticsEvent>  $events
     * @return list<array{
     *     label: string,
     *     event_name: string,
     *     path: string|null,
     *     occurred_at: string|null,
     *     event_count: int
     * }>
     */
    public function buildStepsWithCounts(Collection $events): array
    {
        if ($events->isEmpty()) {
            return [];
        }

        $sorted = $events
            ->sortBy(fn (AnalyticsEvent $event): array => [
                (string) $event->getRawOriginal('occurred_at'),
                (int) $event->id,
            ])
            ->values();

        /** @var array<string, array{label: string, event_name: string, path: string|null, occurred_at: string|null, event_count: int}> $stepsByKey */
        $stepsByKey = [];
        $stepOrder = [];

        foreach ($sorted as $event) {
            $stepKey = $this->stepKey($event);
            $occurredAt = $event->getRawOriginal('occurred_at');
            $occurredAtIso = $occurredAt !== null
                ? Carbon::parse((string) $occurredAt, 'UTC')->toIso8601String()
                : null;

            if (! isset($stepsByKey[$stepKey])) {
                $stepOrder[] = $stepKey;
                $stepsByKey[$stepKey] = [
                    'label' => $this->pageLabel($event),
                    'event_name' => (string) $event->event_name,
                    'path' => filled($event->path) ? (string) $event->path : null,
                    'occurred_at' => $occurredAtIso,
                    'event_count' => 0,
                ];
            }

            $stepsByKey[$stepKey]['event_count']++;
        }

        return array_map(
            static fn (string $stepKey): array => $stepsByKey[$stepKey],
            $stepOrder,
        );
    }

    /**
     * @return list<array{
     *     label: string,
     *     event_name: string,
     *     path: string|null,
     *     occurred_at: string|null
     * }>
     */
    public function buildSteps(Collection $events): array
    {
        if ($events->isEmpty()) {
            return [];
        }

        $sorted = $events
            ->sortBy(fn (AnalyticsEvent $event): array => [
                (string) $event->getRawOriginal('occurred_at'),
                (int) $event->id,
            ])
            ->values();

        $steps = [];
        $lastStepKey = null;

        foreach ($sorted as $event) {
            $stepKey = $this->stepKey($event);

            if ($stepKey === $lastStepKey) {
                continue;
            }

            $occurredAt = $event->getRawOriginal('occurred_at');

            $steps[] = [
                'label' => $this->pageLabel($event),
                'event_name' => (string) $event->event_name,
                'path' => filled($event->path) ? (string) $event->path : null,
                'occurred_at' => $occurredAt !== null
                    ? Carbon::parse((string) $occurredAt, 'UTC')->toIso8601String()
                    : null,
            ];

            $lastStepKey = $stepKey;
        }

        return $steps;
    }

    /**
     * @param  Collection<int, AnalyticsEvent>  $events
     * @return array{
     *     referrer_domain: string|null,
     *     campaign_code: string|null,
     *     utm_source: string|null,
     *     utm_medium: string|null,
     *     utm_campaign: string|null,
     *     path: string|null,
     *     event_name: string|null,
     *     page_label: string|null,
     *     occurred_at: string|null
     * }
     */
    public function buildEntry(Collection $events): array
    {
        if ($events->isEmpty()) {
            return [
                'referrer_domain' => null,
                'campaign_code' => null,
                'utm_source' => null,
                'utm_medium' => null,
                'utm_campaign' => null,
                'path' => null,
                'event_name' => null,
                'page_label' => null,
                'occurred_at' => null,
            ];
        }

        $first = $events
            ->sortBy(fn (AnalyticsEvent $event): array => [
                (string) $event->getRawOriginal('occurred_at'),
                (int) $event->id,
            ])
            ->first();

        $occurredAt = $first->getRawOriginal('occurred_at');

        return [
            'referrer_domain' => filled($first->referrer_domain) ? (string) $first->referrer_domain : null,
            'campaign_code' => filled($first->campaign_code) ? (string) $first->campaign_code : null,
            'utm_source' => filled($first->utm_source) ? (string) $first->utm_source : null,
            'utm_medium' => filled($first->utm_medium) ? (string) $first->utm_medium : null,
            'utm_campaign' => filled($first->utm_campaign) ? (string) $first->utm_campaign : null,
            'path' => filled($first->path) ? (string) $first->path : null,
            'event_name' => filled($first->event_name) ? (string) $first->event_name : null,
            'page_label' => $this->pageLabel($first),
            'occurred_at' => $occurredAt !== null
                ? Carbon::parse((string) $occurredAt, 'UTC')->toIso8601String()
                : null,
        ];
    }

    /**
     * Etykieta kolumny „Wejście” na dashboardzie (referrer → kampania → UTM → direct).
     *
     * @param  array{
     *     referrer_domain?: string|null,
     *     campaign_code?: string|null,
     *     utm_source?: string|null,
     *     utm_medium?: string|null
     * }  $entry
     */
    public function formatEntryDisplayLabel(array $entry): string
    {
        $parts = [];

        if (filled($entry['referrer_domain'] ?? null)) {
            $parts[] = (string) $entry['referrer_domain'];
        }

        if (filled($entry['campaign_code'] ?? null)) {
            $parts[] = 'kamp. '.(string) $entry['campaign_code'];
        }

        if ($parts !== []) {
            return implode(' · ', $parts);
        }

        if (filled($entry['utm_source'] ?? null)) {
            $label = (string) $entry['utm_source'];
            if (filled($entry['utm_medium'] ?? null)) {
                $label .= ' / '.(string) $entry['utm_medium'];
            }

            return $label;
        }

        return 'direct (bezpośrednio)';
    }

    /**
     * @param  list<array{label: string, event_count?: int}>  $steps
     */
    public function compactJourneyLabel(array $steps): string
    {
        if ($steps === []) {
            return '—';
        }

        return implode(' → ', array_map(
            static fn (array $step): string => (string) ($step['label'] ?? '—'),
            $steps,
        ));
    }

    /**
     * Ścieżka z licznikiem zdarzeń przy każdym kroku, gdy count &gt; 1, np. „Opis (3) → Formularz (4)”.
     *
     * @param  list<array{label: string, event_count?: int}>  $steps
     */
    public function compactJourneyLabelWithCounts(array $steps): string
    {
        if ($steps === []) {
            return '—';
        }

        $parts = [];

        foreach ($steps as $step) {
            $label = (string) ($step['label'] ?? '—');
            $count = (int) ($step['event_count'] ?? 1);

            if ($count > 1) {
                $label .= ' ('.$count.')';
            }

            $parts[] = $label;
        }

        return implode(' → ', $parts);
    }

    /**
     * @param  list<array{label: string, event_count?: int}>  $steps
     *
     * @deprecated Use compactJourneyLabelWithCounts()
     */
    public function compactJourneyLabelWithCurrentCount(array $steps): string
    {
        return $this->compactJourneyLabelWithCounts($steps);
    }

    /**
     * @param  list<array{label: string, event_count?: int}>  $steps
     */
    public function currentStepEventCount(array $steps): int
    {
        if ($steps === []) {
            return 0;
        }

        $last = $steps[array_key_last($steps)];

        return (int) ($last['event_count'] ?? 1);
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
            'form_order_created' => $this->orderSubmittedPageLabel($event),
            'campaign_short_link_visit' => 'Link kampanii',
            'campaign_redirect_resolved' => 'Przekierowanie kampanii',
            default => match ($event->landing_target) {
                'course_description' => 'Opis szkolenia',
                'order_form_direct' => 'Formularz zamówienia',
                default => filled($event->path) ? (string) $event->path : 'Lejek sprzedaży',
            },
        };
    }

    public function shortSessionId(string $sessionId): string
    {
        $sessionId = trim($sessionId);

        if ($sessionId === '') {
            return '—';
        }

        if (strlen($sessionId) <= 8) {
            return $sessionId;
        }

        return '…'.substr($sessionId, -4);
    }

    private function stepKey(AnalyticsEvent $event): string
    {
        return implode('|', [
            $this->pageLabel($event),
            (string) ($event->path ?? ''),
            (string) ($event->course_id ?? ''),
        ]);
    }

    private function orderSubmittedPageLabel(AnalyticsEvent $event): string
    {
        $metadata = is_array($event->metadata) ? $event->metadata : [];
        $flow = $metadata['order_flow'] ?? $metadata['payment_type'] ?? null;

        return match ($flow) {
            'online' => 'Złożył zamówienie (online)',
            'deferred', 'deferred_invoice' => 'Złożył zamówienie (odroczone)',
            default => 'Złożył zamówienie',
        };
    }
}
