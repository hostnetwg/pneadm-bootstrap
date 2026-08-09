<?php

namespace App\Support;

/**
 * Przykładowe awatary rekomendacji (serwowane z pnedu.pl/images/avatars/).
 * Zestaw „nauczycielski” (16 SVG) — DiceBear Avataaars.
 */
final class SurveyAvatarPresets
{
    public const NONE = 'none';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::definitions(), 'key');
    }

    /**
     * Domyślnie włączone w formularzu (rdzeń 16). Reszta katalogu — do włączenia w ustawieniach.
     *
     * @return list<string>
     */
    public static function defaultEnabledKeys(): array
    {
        return [
            'woman-straight-brown',
            'woman-straight-blonde',
            'woman-bob-dark',
            'woman-bob-glasses',
            'woman-long-glasses',
            'woman-curly-brown',
            'woman-short-waved',
            'woman-hijab',
            'man-short-flat',
            'man-short-round',
            'man-caesar',
            'man-glasses',
            'man-beard',
            'man-beard-glasses',
            'man-gray',
            'man-bald-beard',
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
            'teacher-f-2' => 'woman-bob-dark',
            'teacher-m-1' => 'man-short-flat',
            'teacher-m-2' => 'man-beard',
            'director-f-1' => 'woman-long-glasses',
            'director-m-1' => 'man-beard-glasses',
            'neutral-1' => 'man-caesar',
            'neutral-2' => 'woman-short-waved',
            'woman-bob-black' => 'woman-bob-dark',
            'woman-curly-auburn' => 'woman-curly-brown',
            'woman-bun-red' => 'woman-bob-dark',
            'woman-big-hair' => 'woman-curly-brown',
            'woman-mia' => 'woman-long-glasses',
            'woman-fro' => 'woman-curly-brown',
            'man-blonde-sides' => 'man-caesar',
            'man-shaggy' => 'man-glasses',
            'man-gray-beard' => 'man-gray',
            'man-moustache' => 'man-beard',
            'man-dreads' => 'man-short-flat',
            default => self::isValid($key) ? $key : null,
        };
    }

    /**
     * @param  list<string>|null  $onlyKeys
     * @return list<array{key: string, url: string, label: string, group: string}>
     */
    public static function options(?array $onlyKeys = null): array
    {
        $allowed = $onlyKeys === null ? null : array_fill_keys($onlyKeys, true);

        $out = [];
        foreach (self::definitions() as $def) {
            if ($allowed !== null && ! isset($allowed[$def['key']])) {
                continue;
            }
            $out[] = [
                'key' => $def['key'],
                'url' => self::url($def['key']),
                'label' => $def['label'],
                'group' => $def['group'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>|null  $onlyKeys
     * @return array<string, list<array{key: string, url: string, label: string, group: string}>>
     */
    public static function optionsByGroup(?array $onlyKeys = null): array
    {
        $grouped = [];
        foreach (self::options($onlyKeys) as $option) {
            $grouped[$option['group']][] = $option;
        }

        return $grouped;
    }

    /**
     * @return list<array{key: string, label: string, group: string}>
     */
    private static function definitions(): array
    {
        return [
            ['key' => 'woman-straight-brown', 'label' => 'Długie, brązowe', 'group' => 'Kobiety'],
            ['key' => 'woman-straight-blonde', 'label' => 'Długie, blond', 'group' => 'Kobiety'],
            ['key' => 'woman-straight-auburn', 'label' => 'Długie, rude', 'group' => 'Kobiety'],
            ['key' => 'woman-long-dark', 'label' => 'Półdługie, ciemne', 'group' => 'Kobiety'],
            ['key' => 'woman-long-glasses', 'label' => 'Długie + okulary', 'group' => 'Kobiety'],
            ['key' => 'woman-long-glasses-blonde', 'label' => 'Blond + okulary', 'group' => 'Kobiety'],
            ['key' => 'woman-bob-dark', 'label' => 'Bob, ciemne', 'group' => 'Kobiety'],
            ['key' => 'woman-bob-blonde', 'label' => 'Bob, blond', 'group' => 'Kobiety'],
            ['key' => 'woman-bob-glasses', 'label' => 'Bob + okulary', 'group' => 'Kobiety'],
            ['key' => 'woman-bob-gray', 'label' => 'Bob, siwe + okulary', 'group' => 'Kobiety'],
            ['key' => 'woman-curly-brown', 'label' => 'Kręcone, brązowe', 'group' => 'Kobiety'],
            ['key' => 'woman-curly-glasses', 'label' => 'Kręcone + okulary', 'group' => 'Kobiety'],
            ['key' => 'woman-short-waved', 'label' => 'Krótkie, falowane', 'group' => 'Kobiety'],
            ['key' => 'woman-short-curly', 'label' => 'Krótkie, kręcone', 'group' => 'Kobiety'],
            ['key' => 'woman-bun-brown', 'label' => 'Kok, brązowe', 'group' => 'Kobiety'],
            ['key' => 'woman-hijab', 'label' => 'Hijab', 'group' => 'Kobiety'],
            ['key' => 'man-short-flat', 'label' => 'Krótkie, gładkie', 'group' => 'Mężczyźni'],
            ['key' => 'man-short-round', 'label' => 'Krótkie, czarne', 'group' => 'Mężczyźni'],
            ['key' => 'man-short-curly', 'label' => 'Krótkie, kręcone', 'group' => 'Mężczyźni'],
            ['key' => 'man-short-light', 'label' => 'Krótkie, jasne', 'group' => 'Mężczyźni'],
            ['key' => 'man-caesar', 'label' => 'Krótka fryzura', 'group' => 'Mężczyźni'],
            ['key' => 'man-caesar-side', 'label' => 'Krótka z przedziałkiem', 'group' => 'Mężczyźni'],
            ['key' => 'man-sides', 'label' => 'Krótko po bokach', 'group' => 'Mężczyźni'],
            ['key' => 'man-glasses', 'label' => 'Krótkie + okulary', 'group' => 'Mężczyźni'],
            ['key' => 'man-glasses-round', 'label' => 'Okulary okrągłe', 'group' => 'Mężczyźni'],
            ['key' => 'man-beard', 'label' => 'Broda', 'group' => 'Mężczyźni'],
            ['key' => 'man-beard-glasses', 'label' => 'Broda + okulary', 'group' => 'Mężczyźni'],
            ['key' => 'man-beard-full', 'label' => 'Pełna broda', 'group' => 'Mężczyźni'],
            ['key' => 'man-gray', 'label' => 'Siwe włosy', 'group' => 'Mężczyźni'],
            ['key' => 'man-gray-beard', 'label' => 'Siwe + broda', 'group' => 'Mężczyźni'],
            ['key' => 'man-bald', 'label' => 'Łysy', 'group' => 'Mężczyźni'],
            ['key' => 'man-bald-beard', 'label' => 'Łysy + broda', 'group' => 'Mężczyźni'],
        ];
    }
}
