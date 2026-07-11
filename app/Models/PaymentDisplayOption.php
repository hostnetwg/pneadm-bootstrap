<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Jedna rekord – ustawienia widoczności opcji płatności na pnedu.pl.
 * Odczyt z tej tabeli wykonuje także aplikacja pnedu (connection pneadm).
 */
class PaymentDisplayOption extends Model
{
    /** Po tym czasie na produkcji sama wyłącza się opcja bez ograniczeń e-mail (bezpiecznik). */
    public const UNRESTRICTED_AUTO_FILL_PRODUCTION_TTL_MINUTES = 1;

    public const SETTINGS_CACHE_KEY = 'payment_display_options';

    public const SETTINGS_CACHE_TTL_SECONDS = 900;

    protected $table = 'payment_display_options';

    protected $attributes = [
        'show_order_form_v2' => false,
        'default_signup_order_form_variant' => 'legacy',
    ];

    protected $fillable = [
        'show_pay_publigo',
        'show_pay_online',
        'show_deferred_order',
        'show_order_form',
        'show_order_form_v2',
        'default_signup_order_form_variant',
        'show_order_form_alt',
        'order_form_auto_fill_test_data',
        'order_form_auto_fill_test_data_enabled_at',
        'order_form_auto_fill_test_data_developers_only',
        'default_post_end_access_duration_value',
        'default_post_end_access_duration_unit',
    ];

    protected $casts = [
        'show_pay_publigo' => 'boolean',
        'show_pay_online' => 'boolean',
        'show_deferred_order' => 'boolean',
        'show_order_form' => 'boolean',
        'show_order_form_v2' => 'boolean',
        'show_order_form_alt' => 'boolean',
        'order_form_auto_fill_test_data' => 'boolean',
        'order_form_auto_fill_test_data_enabled_at' => 'datetime',
        'order_form_auto_fill_test_data_developers_only' => 'boolean',
        'default_post_end_access_duration_value' => 'integer',
    ];

    public static function forgetSettingsCache(): void
    {
        Cache::forget(self::SETTINGS_CACHE_KEY);
    }

    /**
     * Zwraca jedyny wiersz ustawień (id = 1). Tworzy go, jeśli nie istnieje.
     * W razie błędu (np. baza niedostępna) zwraca obiekt z domyślnymi wartościami, żeby widok się nie wywalił.
     */
    public static function getSettings(): self
    {
        $settings = Cache::remember(
            self::SETTINGS_CACHE_KEY,
            self::SETTINGS_CACHE_TTL_SECONDS,
            fn () => self::loadSettingsFromDatabase(),
        );

        return self::expireUnrestrictedAutoFillIfNeeded($settings);
    }

    public static function unrestrictedAutoFillShouldExpire(): bool
    {
        return app()->environment('production');
    }

    public static function isUnrestrictedAutoFillExpired(?\Illuminate\Support\Carbon $enabledAt): bool
    {
        if ($enabledAt instanceof \Illuminate\Support\Carbon) {
            return $enabledAt->lt(now()->subMinutes(self::UNRESTRICTED_AUTO_FILL_PRODUCTION_TTL_MINUTES));
        }

        return true;
    }

    /**
     * Na produkcji wyłącza opcję bez ograniczeń e-mail po UNRESTRICTED_AUTO_FILL_PRODUCTION_TTL_MINUTES.
     * Opcja developers_only nie ma auto-wygaśnięcia.
     */
    public static function expireUnrestrictedAutoFillIfNeeded(self $row): self
    {
        if (! (bool) ($row->order_form_auto_fill_test_data ?? false)) {
            return $row;
        }

        if (! self::unrestrictedAutoFillShouldExpire()) {
            return $row;
        }

        if (! self::isUnrestrictedAutoFillExpired($row->order_form_auto_fill_test_data_enabled_at)) {
            return $row;
        }

        $row->forceFill([
            'order_form_auto_fill_test_data' => false,
            'order_form_auto_fill_test_data_enabled_at' => null,
        ])->save();

        self::forgetSettingsCache();

        return $row->fresh() ?? $row;
    }

    private static function loadSettingsFromDatabase(): self
    {
        try {
            $row = self::first();
            if ($row) {
                return $row;
            }

            return self::create([
                'show_pay_publigo' => true,
                'show_pay_online' => true,
                'show_deferred_order' => true,
                'show_order_form' => true,
                'show_order_form_v2' => false,
                'default_signup_order_form_variant' => 'legacy',
                'show_order_form_alt' => true,
                'order_form_auto_fill_test_data' => false,
                'order_form_auto_fill_test_data_enabled_at' => null,
                'order_form_auto_fill_test_data_developers_only' => false,
                'default_post_end_access_duration_value' => 2,
                'default_post_end_access_duration_unit' => 'months',
            ]);
        } catch (\Throwable $e) {
            report($e);
            $fallback = new self;
            $fallback->show_pay_publigo = true;
            $fallback->show_pay_online = true;
            $fallback->show_deferred_order = true;
            $fallback->show_order_form = true;
            $fallback->show_order_form_v2 = false;
            $fallback->default_signup_order_form_variant = 'legacy';
            $fallback->show_order_form_alt = true;
            $fallback->order_form_auto_fill_test_data = false;
            $fallback->order_form_auto_fill_test_data_enabled_at = null;
            $fallback->order_form_auto_fill_test_data_developers_only = false;
            $fallback->default_post_end_access_duration_value = 2;
            $fallback->default_post_end_access_duration_unit = 'months';

            return $fallback;
        }
    }
}
