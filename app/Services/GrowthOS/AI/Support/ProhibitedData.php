<?php

namespace App\Services\GrowthOS\AI\Support;

final class ProhibitedData
{
    public static function contains(string $value): bool
    {
        return preg_match('/[\w.+-]+@[\w.-]+\.[a-z]{2,}/iu', $value) === 1
            || preg_match('/(?<!\d)(?:\+?48[\s.-]?)?(?:\d[\s.-]?){9}(?!\d)/u', $value) === 1
            || preg_match('/\b(?:api[_ -]?key|bearer\s+[a-z0-9._-]+|hasło|password|token\s*[:=])\b/iu', $value) === 1;
    }

    /**
     * Dates and times look like phone numbers to the digit pattern, so they are removed first.
     */
    public static function containsIgnoringDates(string $value): bool
    {
        $withoutDates = preg_replace(
            [
                '/\b\d{4}-\d{1,2}-\d{1,2}\b/u',
                '/\b\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}\b/u',
                '/\b\d{1,2}:\d{2}\b/u',
            ],
            ' ',
            $value,
        ) ?? $value;

        return self::contains($withoutDates);
    }

    /**
     * @return list<string>
     */
    public static function urls(string $value): array
    {
        preg_match_all('/\b(?:https?:\/\/|www\.)[^\s<>()"\']+/iu', $value, $matches);

        return array_values(array_unique(array_map(
            static fn (string $url): string => rtrim(mb_strtolower($url), '.,;:!?'),
            $matches[0],
        )));
    }
}
