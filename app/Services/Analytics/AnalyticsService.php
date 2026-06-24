<?php

namespace App\Services\Analytics;

use App\Enums\Analytics\AnalyticsEventName;
use App\Jobs\Analytics\StoreAnalyticsEventJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AnalyticsService
{
    public function __construct(
        private readonly AnalyticsModeResolver $modeResolver,
        private readonly AnalyticsPayloadSanitizer $sanitizer,
    ) {}

    public function track(AnalyticsEventName|string $eventName, array $payload = [], ?string $mode = null): bool
    {
        $eventNameValue = $eventName instanceof AnalyticsEventName ? $eventName->value : $eventName;
        $eventCategory = $eventName instanceof AnalyticsEventName ? $eventName->category()->value : ($payload['event_category'] ?? 'system');
        $analyticsSessionId = $payload['analytics_session_id'] ?? null;

        try {
            if (! $this->modeResolver->shouldTrack($eventName, $mode, is_string($analyticsSessionId) ? $analyticsSessionId : null)) {
                return false;
            }

            $payload = array_merge($payload, [
                'event_uuid' => $payload['event_uuid'] ?? (string) Str::uuid(),
                'event_name' => $eventNameValue,
                'event_category' => $eventCategory,
                'occurred_at' => $payload['occurred_at'] ?? now()->toDateTimeString(),
                'app_source' => $payload['app_source'] ?? config('app.name', 'laravel'),
            ]);

            $sanitizedPayload = $this->sanitizer->sanitize($payload);

            StoreAnalyticsEventJob::dispatch($sanitizedPayload);

            return true;
        } catch (Throwable $exception) {
            $this->logFailure($eventNameValue, $exception);

            return false;
        }
    }

    private function logFailure(string $eventName, Throwable $exception): void
    {
        try {
            Log::warning('Analytics tracking skipped.', [
                'event_name' => $eventName,
                'exception_class' => $exception::class,
            ]);
        } catch (Throwable) {
            // Logging must not break fail-silent analytics.
        }
    }
}
