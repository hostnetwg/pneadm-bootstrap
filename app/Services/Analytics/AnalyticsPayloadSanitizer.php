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
        'school_name',
        'company_name',
        'surname',
        'tax_id',
        'regon',
        'raw_response',
        'exception_message',
        'input_value',
        'old_value',
        'new_value',
        'token',
        'fbclid',
        'gclid',
        'msclkid',
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
        'tracking_schema_version',
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
        'completed_fields_count',
        'completed_sections_count',
        'cta_key',
        'error_group',
        'error_rule',
        'error_codes',
        'error_count',
        'error_fields',
        'error_sections',
        'errors_count',
        'field_key',
        'field_keys',
        'field_state',
        'field_type',
        'first_error_field',
        'first_error_section',
        'first_field_key',
        'first_interaction_type',
        'first_section_key',
        'fields_count',
        'form_session_id',
        'form_order_status',
        'gus_lookup_success',
        'gus_lookup_used',
        'has_price_variant',
        'has_recipient',
        'has_value',
        'invoice_path_type',
        'invoice_role_option',
        'ksef_option_selected',
        'landing_target',
        'last_activity_type',
        'last_event_name',
        'last_field_key',
        'last_section_key',
        'latency_ms',
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
        'required_fields_count',
        'route_name',
        'rule_key',
        'sample_rate',
        'section_keys',
        'section_key',
        'seconds_from_page_load',
        'selected_payment_method',
        'error_type',
        'fields_applied_count',
        'fields_returned_count',
        'gus_target',
        'http_status',
        'nip_present',
        'nip_format_valid_client',
        'overwritten_manual_fields_count',
        'response_source',
        'result_type',
        'retry_possible',
        'seconds_after_gus_error',
        'seconds_after_gus_success',
        'started_at',
        'target',
        'time_spent_seconds',
        'tracking_schema_version',
        'tracking_mode',
        'trigger',
        'utm_campaign',
        'utm_content',
        'utm_medium',
        'utm_source',
        'utm_term',
        'validation_context',
        'validation_error_codes',
        'visible_validation_errors_count',
        'traffic_channel',
        'traffic_source',
        'traffic_medium',
        'traffic_campaign',
        'attribution_source',
        'conversion_reporting_channel',
        'first_touch_channel',
        'last_external_touch_channel',
        'internal_promo_touched',
        'fbclid_present',
        'gclid_present',
        'msclkid_present',
        'current_source',
        'current_medium',
        'current_campaign',
        'current_content',
        'current_term',
        'current_referrer',
        'current_referrer_domain',
        'current_url',
        'current_channel',
        'current_attribution_source',
        'first_touch_source',
        'first_touch_medium',
        'first_touch_campaign',
        'first_touch_content',
        'first_touch_term',
        'first_touch_referrer',
        'first_touch_referrer_domain',
        'first_touch_landing_url',
        'first_touch_attribution_source',
        'last_touch_source',
        'last_touch_medium',
        'last_touch_campaign',
        'last_touch_content',
        'last_touch_term',
        'last_touch_referrer',
        'last_touch_referrer_domain',
        'last_touch_landing_url',
        'last_touch_channel',
        'last_touch_attribution_source',
        'last_external_touch_source',
        'last_external_touch_medium',
        'last_external_touch_campaign',
        'last_external_touch_content',
        'last_external_touch_term',
        'last_external_touch_referrer',
        'last_external_touch_referrer_domain',
        'last_external_touch_landing_url',
        'last_external_touch_attribution_source',
        'internal_touch_source',
        'internal_touch_medium',
        'internal_touch_context',
        'internal_touch_path',
        'internal_promo_placement',
        'internal_promo_context',
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

            if (in_array($normalizedKey, [
                'field_keys',
                'section_keys',
                'error_codes',
                'error_fields',
                'error_sections',
                'validation_error_codes',
            ], true)) {
                $sanitized[$normalizedKey] = is_array($value)
                    ? $this->sanitizeList($value)
                    : $this->normalizeValue($value);

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

    private function sanitizeList(array $values): array
    {
        $sanitized = [];

        foreach ($values as $value) {
            if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                continue;
            }

            $normalizedValue = $this->normalizeValue((string) $value);
            if ($normalizedValue !== null && $normalizedValue !== '') {
                $sanitized[] = $normalizedValue;
            }
        }

        return array_values(array_unique($sanitized));
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

    private const ALLOWED_KEYS_OVERRIDING_FORBIDDEN_PARTS = [
        'nip_present',
        'nip_format_valid_client',
        'fbclid_present',
        'gclid_present',
        'msclkid_present',
    ];

    private function isForbiddenKey(string $key): bool
    {
        $normalizedKey = $this->normalizeKey($key);

        if (in_array($normalizedKey, self::ALLOWED_KEYS_OVERRIDING_FORBIDDEN_PARTS, true)) {
            return false;
        }

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
