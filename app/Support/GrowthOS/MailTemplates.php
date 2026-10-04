<?php

namespace App\Support\GrowthOS;

/**
 * Built-in main-mail layouts. HTML shells live in resources/growth-os/mail-templates.
 * The key lives in the material JSON payload, not in its own table (DEC-049).
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
}
