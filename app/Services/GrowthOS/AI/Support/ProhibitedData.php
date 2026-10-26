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
            static fn (string $url): string => self::normalizeUrlToken($url),
            $matches[0],
        )));
    }

    /**
     * Keep URLs already present in the allow-listed input; replace or remove the rest.
     *
     * @param  list<string>  $allowedUrls
     */
    public static function replaceDisallowedUrls(string $value, array $allowedUrls, string $replacement = ''): string
    {
        $allowed = array_values(array_unique(array_map(
            static fn (string $url): string => self::normalizeUrlToken($url),
            $allowedUrls,
        )));

        $replaced = preg_replace_callback(
            '/\b(?:https?:\/\/|www\.)[^\s<>()"\']+/iu',
            static function (array $match) use ($allowed, $replacement): string {
                $raw = $match[0];
                $trailing = '';
                if (preg_match('/^(.*?)([.,;:!?]+)$/u', $raw, $parts) === 1) {
                    $raw = $parts[1];
                    $trailing = $parts[2];
                }
                $normalized = self::normalizeUrlToken($raw);

                return (in_array($normalized, $allowed, true) ? $raw : $replacement).$trailing;
            },
            $value,
        ) ?? $value;

        $replaced = preg_replace('/[ \t]+\n/u', "\n", $replaced) ?? $replaced;
        $replaced = preg_replace('/\n{3,}/u', "\n\n", $replaced) ?? $replaced;
        $replaced = preg_replace('/[ \t]{2,}/u', ' ', $replaced) ?? $replaced;

        return trim($replaced);
    }

    private static function normalizeUrlToken(string $url): string
    {
        return rtrim(mb_strtolower($url), '.,;:!?');
    }
}
