<?php

namespace App\Services\Analytics;

use App\Enums\Analytics\AnalyticsEventName;
use App\Enums\Analytics\AnalyticsMode;
use App\Models\AnalyticsSetting;

class AnalyticsModeResolver
{
    public function resolve(?string $mode = null): AnalyticsMode
    {
        // Hard kill switch: .env/config ANALYTICS_ENABLED=false ma absolutny priorytet nad runtime override.
        if (! config('analytics.enabled', true)) {
            return AnalyticsMode::Off;
        }

        // Runtime override z bazy pneadm (fail-safe: brak/awaria => null => użyj .env/config).
        if (AnalyticsSetting::enabledOverride() === false) {
            return AnalyticsMode::Off;
        }

        $effectiveMode = $mode
            ?: AnalyticsSetting::defaultModeOverride()
            ?: config('analytics.default_mode', 'standard');

        return AnalyticsMode::fromConfig($effectiveMode);
    }

    public function shouldTrack(AnalyticsEventName|string $eventName, ?string $mode = null, ?string $analyticsSessionId = null): bool
    {
        $resolvedMode = $this->resolve($mode);

        if ($resolvedMode === AnalyticsMode::Off) {
            return false;
        }

        if (! $this->eventAllowedInMode($this->eventNameValue($eventName), $resolvedMode)) {
            return false;
        }

        return $this->passesSampling($analyticsSessionId, (int) config('analytics.sample_rate', 100));
    }

    public function eventAllowedInMode(string $eventName, AnalyticsMode $mode): bool
    {
        if ($mode === AnalyticsMode::Full || $mode === AnalyticsMode::Standard) {
            return true;
        }

        $lightEvents = [
            AnalyticsEventName::CampaignShortLinkVisit->value,
            AnalyticsEventName::CampaignRedirectResolved->value,
            AnalyticsEventName::CourseDescriptionViewed->value,
            AnalyticsEventName::OrderFormViewed->value,
            AnalyticsEventName::OrderFormSubmitAttempted->value,
            AnalyticsEventName::FormOrderCreated->value,
            AnalyticsEventName::OnlinePaymentSelected->value,
            AnalyticsEventName::DeferredInvoiceSelected->value,
            AnalyticsEventName::PaymentOrderCreated->value,
            AnalyticsEventName::PaymentStatusChanged->value,
            AnalyticsEventName::InvoiceCreated->value,
            AnalyticsEventName::OrderFormStarted->value,
            AnalyticsEventName::OrderFormSubmitClicked->value,
            AnalyticsEventName::FormFirstInteraction->value,
            AnalyticsEventName::FormSubmitClicked->value,
            AnalyticsEventName::ServerSubmitAttempted->value,
            AnalyticsEventName::OrderCreated->value,
        ];

        if ($mode === AnalyticsMode::Light) {
            return in_array($eventName, $lightEvents, true);
        }

        $aggregateOnlyEvents = [
            AnalyticsEventName::CampaignShortLinkVisit->value,
            AnalyticsEventName::CourseDescriptionViewed->value,
            AnalyticsEventName::OrderFormViewed->value,
            AnalyticsEventName::FormOrderCreated->value,
            AnalyticsEventName::OrderCreated->value,
        ];

        return $mode === AnalyticsMode::AggregateOnly
            && in_array($eventName, $aggregateOnlyEvents, true);
    }

    public function passesSampling(?string $analyticsSessionId, int $sampleRate): bool
    {
        if ($sampleRate >= 100) {
            return true;
        }

        if ($sampleRate <= 0) {
            return false;
        }

        $subject = $analyticsSessionId ?: 'anonymous';

        return (crc32($subject) % 100) < $sampleRate;
    }

    private function eventNameValue(AnalyticsEventName|string $eventName): string
    {
        return $eventName instanceof AnalyticsEventName ? $eventName->value : $eventName;
    }
}
