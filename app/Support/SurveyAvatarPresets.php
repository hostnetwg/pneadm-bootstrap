<?php

namespace App\Support;

/**
 * Przykładowe awatary rekomendacji (serwowane z pnedu.pl/images/avatars/).
 * Styl: DiceBear Avataaars (Pablo Stanley) — free for commercial use.
 */
final class SurveyAvatarPresets
{
    public const NONE = 'none';

    /** @return list<string> */
    public static function keys(): array
    {
        return [
            'woman-straight-brown', 'woman-straight-blonde', 'woman-bob-black', 'woman-curly-auburn',
            'woman-bun-red', 'woman-big-hair', 'woman-short-waved', 'woman-long-glasses',
            'woman-bob-glasses', 'woman-mia', 'woman-fro', 'woman-hijab',
            'man-short-flat', 'man-short-round', 'man-blonde-sides', 'man-shaggy',
            'man-glasses', 'man-beard', 'man-beard-glasses', 'man-gray-beard',
            'man-moustache', 'man-dreads', 'man-bald', 'man-bald-beard',
        ];
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && $key !== '' && in_array($key, self::keys(), true);
    }

    public static function publicPath(string $key): string
    {
        return 'images/avatars/'.$key.'.svg';
    }

    public static function url(string $key): string
    {
        $base = rtrim((string) config('services.pnedu_frontend_url', 'https://pnedu.pl'), '/');

        return $base.'/'.self::publicPath($key);
    }

    public static function defaultKey(): string
    {
        return 'woman-straight-brown';
    }

    public static function migrateLegacyKey(?string $key): ?string
    {
        return match ($key) {
            'teacher-f-1' => 'woman-straight-brown',
            'teacher-f-2' => 'woman-bob-black',
            'teacher-m-1' => 'man-short-flat',
            'teacher-m-2' => 'man-beard',
            'director-f-1' => 'woman-long-glasses',
            'director-m-1' => 'man-beard-glasses',
            'neutral-1' => 'man-bald',
            'neutral-2' => 'woman-short-waved',
            default => self::isValid($key) ? $key : null,
        };
    }
}
