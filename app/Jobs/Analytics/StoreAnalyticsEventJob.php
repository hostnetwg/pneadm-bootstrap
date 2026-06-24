<?php

namespace App\Jobs\Analytics;

use App\Models\Analytics\AnalyticsEvent;
use App\Services\Analytics\AnalyticsPayloadSanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class StoreAnalyticsEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(public array $payload)
    {
        $this->tries = (int) config('analytics.queue.tries', 2);
        $this->timeout = (int) config('analytics.queue.timeout', 30);
        $this->onConnection((string) config('analytics.queue.connection', 'redis'));
        $this->onQueue((string) config('analytics.queue.name', 'analytics'));
    }

    public function handle(AnalyticsPayloadSanitizer $sanitizer): void
    {
        try {
            $payload = $sanitizer->sanitize($this->payload);

            if (! isset($payload['event_uuid'], $payload['event_name'], $payload['event_category'], $payload['occurred_at'])) {
                return;
            }

            AnalyticsEvent::query()->insertOrIgnore([
                'event_uuid' => $payload['event_uuid'],
                'event_name' => $payload['event_name'],
                'event_category' => $payload['event_category'],
                'occurred_at' => $payload['occurred_at'],
                'app_source' => $payload['app_source'] ?? config('app.name', 'laravel'),
                'analytics_session_id' => $payload['analytics_session_id'] ?? null,
                'course_id' => $payload['course_id'] ?? null,
                'course_title_snapshot' => $payload['course_title_snapshot'] ?? null,
                'campaign_id' => $payload['campaign_id'] ?? null,
                'campaign_code' => $payload['campaign_code'] ?? null,
                'landing_target' => $payload['landing_target'] ?? null,
                'campaign_content_depth' => $payload['campaign_content_depth'] ?? null,
                'campaign_channel' => $payload['campaign_channel'] ?? null,
                'cta_type' => $payload['cta_type'] ?? null,
                'utm_source' => $payload['utm_source'] ?? null,
                'utm_medium' => $payload['utm_medium'] ?? null,
                'utm_campaign' => $payload['utm_campaign'] ?? null,
                'utm_content' => $payload['utm_content'] ?? null,
                'utm_term' => $payload['utm_term'] ?? null,
                'order_form_session_id' => $payload['order_form_session_id'] ?? null,
                'form_order_id' => $payload['form_order_id'] ?? null,
                'payment_order_id' => $payload['payment_order_id'] ?? null,
                'ab_test_id' => $payload['ab_test_id'] ?? null,
                'ab_variant_id' => $payload['ab_variant_id'] ?? null,
                'route_name' => $payload['route_name'] ?? null,
                'path' => $payload['path'] ?? null,
                'referrer_domain' => $payload['referrer_domain'] ?? null,
                'device_type' => $payload['device_type'] ?? null,
                'browser_family' => $payload['browser_family'] ?? null,
                'metadata' => isset($payload['metadata']) ? json_encode($payload['metadata'], JSON_THROW_ON_ERROR) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->logFailure($exception);
        }
    }

    private function logFailure(Throwable $exception): void
    {
        try {
            Log::warning('Analytics event was not stored.', [
                'event_name' => $this->payload['event_name'] ?? null,
                'event_uuid' => $this->payload['event_uuid'] ?? null,
                'exception_class' => $exception::class,
            ]);
        } catch (Throwable) {
            // Analytics storage errors must stay isolated from business flows.
        }
    }
}
