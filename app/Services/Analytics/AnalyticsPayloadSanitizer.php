<?php

namespace App\Services\Analytics;

use BackedEnum;

class AnalyticsPayloadSanitizer
{
    private const FORBIDDEN_KEY_PARTS = [
        'address',
        'buyer_name',
        'email',
        'first_name',
        'full_name',
        'invoice_data',
        'last_name',
        'nip',
        'participant_name',
        'phone',
        'recipient_name',
        'surname',
        'token',
    ];

    private const ALLOWED_TOP_LEVEL_KEYS = [
        'ab_test_id',
        'ab_variant_id',
        'analytics_session_id',
        'amount_snapshot',
        'app_source',
        'browser_family',
        'campaign_channel',
        'campaign_code',
        'campaign_content_depth',
        'campaign_id',
        'course_id',
        'course_title_snapshot',
        'cta_type',
        'device_type',
        'event_category',
        'event_name',
        'event_uuid',
        'form_order_id',
        'landing_target',
        'metadata',
        'metadata_json',
        'occurred_at',
        'order_form_session_id',
        'path',
        'payment_order_id',
        'referrer_domain',
        'route_name',
        'utm_campaign',
        'utm_content',
        'utm_medium',
        'utm_source',
        'utm_term',
    ];

    private const ALLOWED_METADATA_KEYS = [
        'amount_gross',
        'buyer_type',
        'campaign_channel',
        'campaign_code',
        'campaign_content_depth',
        'cta_type',
        'device_type',
        'duration_ms_bucket',
        'error_group',
        'error_rule',
        'field_key',
        'field_state',
        'fields_count',
        'gus_lookup_success',
        'gus_lookup_used',
        'has_recipient',
        'invoice_path_type',
        'invoice_role_option',
        'ksef_option_selected',
        'landing_target',
        'lookup_target',
        'order_flow',
        'participant_count',
        'participant_count_bucket',
        'payment_gateway',
        'payment_status',
        'payment_type',
        'profile_scope',
        'profile_source',
        'query_parameters',
        'recipient_section_used',
        'referrer_domain',
        'required_fields_completed',
        'route_name',
        'rule_key',
        'sample_rate',
        'section_key',
        'time_spent_seconds',
        'tracking_mode',
        'utm_campaign',
        'utm_content',
        'utm_medium',
        'utm_source',
        'utm_term',
    ];

    private const ALLOWED_QUERY_KEYS = [
        'campaign_code',
        'utm_campaign',
        'utm_content',
        'utm_medium',
        'utm_source',
        'utm_term',
    ];

    public function sanitize(array $payload): array
    {
        $payload = $this->deriveSafeRequestFields($payload);
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = $this->normalizeKey((string) $key);

            if (! in_array($normalizedKey, self::ALLOWED_TOP_LEVEL_KEYS, true) || $this->isForbiddenKey($normalizedKey)) {
                continue;
            }

            if (in_array($normalizedKey, ['metadata', 'metadata_json'], true)) {
                $sanitized['metadata'] = is_array($value) ? $this->sanitizeMetadata($value) : null;

                continue;
            }

            $sanitized[$normalizedKey] = $this->normalizeValue($value);
        }

        return array_filter($sanitized, static fn ($value): bool => $value !== null && $value !== '');
    }

    public function containsForbiddenKeys(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if ($this->isForbiddenKey((string) $key)) {
                return true;
            }

            if (is_array($value) && $this->containsForbiddenKeys($value)) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $normalizedKey = $this->normalizeKey((string) $key);

            if (! in_array($normalizedKey, self::ALLOWED_METADATA_KEYS, true) || $this->isForbiddenKey($normalizedKey)) {
                continue;
            }

            if ($normalizedKey === 'query_parameters') {
                $sanitized[$normalizedKey] = is_array($value) ? $this->sanitizeQueryParameters($value) : [];

                continue;
            }

            $sanitized[$normalizedKey] = is_array($value)
                ? $this->sanitizeMetadata($value)
                : $this->normalizeValue($value);
        }

        return array_filter($sanitized, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private function sanitizeQueryParameters(array $parameters): array
    {
        $sanitized = [];

        foreach ($parameters as $key => $value) {
            $normalizedKey = $this->normalizeKey((string) $key);

            if (in_array($normalizedKey, self::ALLOWED_QUERY_KEYS, true)) {
                $sanitized[$normalizedKey] = $this->normalizeValue($value);
            }
        }

        return array_filter($sanitized, static fn ($value): bool => $value !== null && $value !== '');
    }

    private function deriveSafeRequestFields(array $payload): array
    {
        if (isset($payload['url']) && ! isset($payload['path']) && is_string($payload['url'])) {
            $payload['path'] = parse_url($payload['url'], PHP_URL_PATH) ?: null;
        }

        if (isset($payload['referrer']) && ! isset($payload['referrer_domain']) && is_string($payload['referrer'])) {
            $payload['referrer_domain'] = parse_url($payload['referrer'], PHP_URL_HOST) ?: null;
        }

        unset($payload['url'], $payload['referrer']);

        return $payload;
    }

    private function isForbiddenKey(string $key): bool
    {
        $normalizedKey = $this->normalizeKey($key);

        foreach (self::FORBIDDEN_KEY_PARTS as $forbidden) {
            if (str_contains($normalizedKey, $forbidden)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeKey(string $key): string
    {
        return strtolower(trim($key));
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_string($value)) {
            return mb_substr(trim($value), 0, 500);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return null;
    }
}
