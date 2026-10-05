<?php

namespace App\Support\GrowthOS;

/**
 * Canonical Sendy PNE mail layout (DEC-050).
 * classic / personal / minimal remain as legacy keys mapped at runtime.
 */
final class MailTemplates
{
    public const CANONICAL = 'sendy-pne';

    public const DEFAULT = self::CANONICAL;

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::CANONICAL => 'Sendy PNE',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    /**
     * @return list<string>
     */
    public static function legacyKeys(): array
    {
        return ['classic', 'personal', 'minimal'];
    }

    public static function key(mixed $value): string
    {
        $value = is_string($value) ? $value : '';

        if ($value === self::CANONICAL || in_array($value, self::legacyKeys(), true)) {
            return self::CANONICAL;
        }

        return self::DEFAULT;
    }

    public static function label(mixed $value): string
    {
        return self::labels()[self::key($value)];
    }

    public static function isLegacy(mixed $value): bool
    {
        return is_string($value) && in_array($value, self::legacyKeys(), true);
    }
}
