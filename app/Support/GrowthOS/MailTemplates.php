<?php

namespace App\Support\GrowthOS;

/**
 * Built-in main-mail layouts. The key lives in the material JSON payload, not in its own table (DEC-049).
 */
final class MailTemplates
{
    public const DEFAULT = 'classic';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'classic' => 'Klasyczny PNE',
            'personal' => 'Osobisty',
            'minimal' => 'Minimalny',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    public static function key(mixed $value): string
    {
        $value = is_string($value) ? $value : '';

        return array_key_exists($value, self::labels()) ? $value : self::DEFAULT;
    }

    public static function label(mixed $value): string
    {
        return self::labels()[self::key($value)];
    }

    /**
     * Visual contract shared by the formatter and the editor frame. Content, the button and the technical preheader stay outside this array.
     *
     * @return array{page: string, card: string, border: string, header: string, header_bg: string, header_color: string, button: string, footer: string, content_pad: string}
     */
    public static function theme(mixed $value): array
    {
        return match (self::key($value)) {
            'personal' => [
                'page' => '#f7f1ea',
                'card' => '#fffdf9',
                'border' => '4px solid #c47b4a',
                'header' => 'List od prowadzącego',
                'header_bg' => '#fffdf9',
                'header_color' => '#8a4b24',
                'button' => '#0f6b4c',
                'footer' => 'Platforma Nowoczesnej Edukacji',
                'content_pad' => '8px 32px 8px 36px',
            ],
            'minimal' => [
                'page' => '#ffffff',
                'card' => '#ffffff',
                'border' => '0',
                'header' => 'PNE',
                'header_bg' => '#ffffff',
                'header_color' => '#6b7785',
                'button' => '#243040',
                'footer' => 'pnedu.pl',
                'content_pad' => '12px 8px 12px 0',
            ],
            default => [
                'page' => '#f4f6f8',
                'card' => '#ffffff',
                'border' => '1px solid #e6eaee',
                'header' => 'Zaproszenie PNE',
                'header_bg' => '#1e4d8c',
                'header_color' => '#ffffff',
                'button' => '#1e4d8c',
                'footer' => 'Platforma Nowoczesnej Edukacji',
                'content_pad' => '28px 28px 8px',
            ],
        };
    }
}
