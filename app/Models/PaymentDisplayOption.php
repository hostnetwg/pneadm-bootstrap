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
    public const SETTINGS_CACHE_KEY = 'payment_display_options';

    public const SETTINGS_CACHE_TTL_SECONDS = 900;

    protected $table = 'payment_display_options';

    protected $fillable = [
        'show_pay_publigo',
        'show_pay_online',
        'show_deferred_order',
        'show_order_form',
        'show_order_form_alt',
        'order_form_auto_fill_test_data',
        'order_form_auto_fill_test_data_enabled_at',
        'default_post_end_access_duration_value',
        'default_post_end_access_duration_unit',
    ];

    protected $casts = [
        'show_pay_publigo' => 'boolean',
        'show_pay_online' => 'boolean',
        'show_deferred_order' => 'boolean',
        'show_order_form' => 'boolean',
        'show_order_form_alt' => 'boolean',
        'order_form_auto_fill_test_data' => 'boolean',
        'order_form_auto_fill_test_data_enabled_at' => 'datetime',
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
        return Cache::remember(
            self::SETTINGS_CACHE_KEY,
            self::SETTINGS_CACHE_TTL_SECONDS,
            fn () => self::loadSettingsFromDatabase(),
        );
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
                'show_order_form_alt' => true,
                'order_form_auto_fill_test_data' => false,
                'order_form_auto_fill_test_data_enabled_at' => null,
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
            $fallback->show_order_form_alt = true;
            $fallback->order_form_auto_fill_test_data = false;
            $fallback->order_form_auto_fill_test_data_enabled_at = null;
            $fallback->default_post_end_access_duration_value = 2;
            $fallback->default_post_end_access_duration_unit = 'months';

            return $fallback;
        }
    }
}
