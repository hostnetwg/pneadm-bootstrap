<?php

namespace App\Support\GrowthOS;

/**
 * HTTPS URL guards for Growth campaign registration / YouTube links (DEC-050).
 */
final class GrowthMailUrls
{
    /**
     * @return list<string|\Illuminate\Validation\Rules\In|string>
     */
    public static function registrationRules(): array
    {
        return ['nullable', 'string', 'max:500', 'url', 'regex:/^https:\/\//i', function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }
            if (self::isDangerous($value)) {
                $fail('Niedozwolony adres URL.');
            }
        }];
    }

    /**
     * @return list<mixed>
     */
    public static function youtubeRules(): array
    {
        return ['nullable', 'string', 'max:500', 'url', 'regex:/^https:\/\//i', function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }
            if (self::isDangerous($value) || ! self::isYoutubeHost($value)) {
                $fail('Podaj bezpieczny adres HTTPS YouTube.');
            }
        }];
    }

    public static function isDangerous(string $url): bool
    {
        $lower = strtolower(trim($url));

        return str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, 'data:')
            || str_starts_with($lower, 'vbscript:');
    }

    public static function isYoutubeHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, [
            'youtube.com',
            'www.youtube.com',
            'm.youtube.com',
            'youtu.be',
            'www.youtu.be',
            'youtube.com.',
        ], true) || str_ends_with($host, '.youtube.com');
    }

    public static function prefersPneduHost(string $url): bool
    {
        $expected = strtolower((string) parse_url((string) config('marketing.pnedu_public_url'), PHP_URL_HOST));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $expected !== '' && $host === $expected;
    }
}
