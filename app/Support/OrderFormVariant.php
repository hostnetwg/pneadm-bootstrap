<?php

namespace App\Support;

final class OrderFormVariant
{
    public const LEGACY = 'legacy';

    public const V2 = 'v2';

    /** Kampania: brama bez form_variant — wariant z ustawień Zakupy pnedu.pl w chwili wejścia. */
    public const GLOBAL = 'global';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::LEGACY, self::V2];
    }

    /**
     * @return list<string>
     */
    public static function campaignValues(): array
    {
        return [self::LEGACY, self::V2, self::GLOBAL];
    }

    public static function normalize(?string $value, string $fallback = self::LEGACY): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, self::values(), true) ? $value : $fallback;
    }

    public static function normalizeCampaignVariant(?string $value, string $fallback = self::LEGACY): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, self::campaignValues(), true) ? $value : $fallback;
    }

    public static function usesGlobalGateway(string $variant): bool
    {
        return strtolower(trim($variant)) === self::GLOBAL;
    }

    /**
     * Wersja zapisana w kampanii — legacy/v2/global bez podmiany (kompatybilność linków /l/ i UTM).
     */
    public static function storedCampaignVariant(?string $value): string
    {
        return self::normalizeCampaignVariant($value);
    }

    public static function pathSegment(string $variant): string
    {
        return 'order-form';
    }

    /**
     * @return array<string, string>
     */
    public static function gatewayQuery(string $variant): array
    {
        if (self::usesGlobalGateway($variant)) {
            return [];
        }

        return ['form_variant' => self::normalize($variant)];
    }

    public static function publicRouteName(): string
    {
        return 'payment.order-form';
    }

    /**
     * @param  array{show_order_form?: bool, show_order_form_v2?: bool}  $visibility
     */
    public static function resolveAvailable(string $preferred, array $visibility): string
    {
        $preferred = self::normalize($preferred);
        $legacyEnabled = (bool) ($visibility['show_order_form'] ?? true);
        $v2Enabled = (bool) ($visibility['show_order_form_v2'] ?? false);

        if ($preferred === self::V2 && $v2Enabled) {
            return self::V2;
        }

        if ($preferred === self::LEGACY && $legacyEnabled) {
            return self::LEGACY;
        }

        if ($v2Enabled) {
            return self::V2;
        }

        if ($legacyEnabled) {
            return self::LEGACY;
        }

        return self::LEGACY;
    }

    public static function label(string $variant): string
    {
        return match (self::normalizeCampaignVariant($variant)) {
            self::V2 => 'Formularz V2 (brama /order-form?form_variant=v2)',
            self::GLOBAL => 'Domyślna globalna (brama /order-form bez form_variant)',
            default => 'Formularz uniwersalny (brama /order-form?form_variant=legacy)',
        };
    }

    /** Krótka etykieta w panelu adm (lista / szczegóły zamówienia). */
    public static function adminShortLabel(?string $stored): string
    {
        if ($stored === null || trim($stored) === '') {
            return 'Legacy (domyślnie)';
        }

        return match (self::normalize($stored)) {
            self::V2 => 'Formularz V2',
            default => 'Formularz legacy',
        };
    }

    /** Klasa Bootstrap badge (bez prefiksu bg-). */
    public static function adminBadgeClass(?string $stored): string
    {
        if ($stored === null || trim($stored) === '') {
            return 'light text-dark border';
        }

        return match (self::normalize($stored)) {
            self::V2 => 'primary',
            default => 'secondary',
        };
    }
}
