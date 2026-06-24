<?php

namespace App\Services\Analytics;

class AnalyticsDebugPayloadInspector
{
    private const FORBIDDEN_KEYS = [
        'address',
        'buyer_name',
        'email',
        'first_name',
        'invoice_data',
        'last_name',
        'name',
        'nip',
        'participant_name',
        'phone',
        'raw_input',
        'raw_referrer',
        'raw_request',
        'raw_url',
        'recipient_name',
        'surname',
        'telefon',
    ];

    /**
     * @return list<string>
     */
    public function forbiddenKeysIn(array $payload): array
    {
        $found = [];
        $this->scan($payload, $found);

        return array_values(array_unique($found));
    }

    public function hasForbiddenKeys(array $payload): bool
    {
        return $this->forbiddenKeysIn($payload) !== [];
    }

    public function redacted(array $payload): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = $this->normalizeKey((string) $key);

            if ($this->isForbiddenKey($normalizedKey)) {
                $redacted[$key] = '[ukryto w panelu debug]';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redacted($value) : $value;
        }

        return $redacted;
    }

    private function scan(array $payload, array &$found): void
    {
        foreach ($payload as $key => $value) {
            $normalizedKey = $this->normalizeKey((string) $key);

            if ($this->isForbiddenKey($normalizedKey)) {
                $found[] = $normalizedKey;
            }

            if (is_array($value)) {
                $this->scan($value, $found);
            }
        }
    }

    private function normalizeKey(string $key): string
    {
        return strtolower(trim($key));
    }

    private function isForbiddenKey(string $key): bool
    {
        return in_array($key, self::FORBIDDEN_KEYS, true);
    }
}
