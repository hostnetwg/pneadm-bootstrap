<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jedna rekord – ustawienia widoczności opcji płatności na pnedu.pl.
 * Odczyt z tej tabeli wykonuje także aplikacja pnedu (connection pneadm).
 */
class PaymentDisplayOption extends Model
{
    protected $table = 'payment_display_options';

    protected $fillable = [
        'show_pay_publigo',
        'show_pay_online',
        'show_deferred_order',
        'show_order_form',
        'show_order_form_alt',
    ];

    protected $casts = [
        'show_pay_publigo' => 'boolean',
        'show_pay_online' => 'boolean',
        'show_deferred_order' => 'boolean',
        'show_order_form' => 'boolean',
        'show_order_form_alt' => 'boolean',
    ];

    /**
     * Zwraca jedyny wiersz ustawień (id = 1). Tworzy go, jeśli nie istnieje.
     * W razie błędu (np. baza niedostępna) zwraca obiekt z domyślnymi wartościami, żeby widok się nie wywalił.
     */
    public static function getSettings(): self
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
            ]);
        } catch (\Throwable $e) {
            report($e);
            $fallback = new self;
            $fallback->show_pay_publigo = true;
            $fallback->show_pay_online = true;
            $fallback->show_deferred_order = true;
            $fallback->show_order_form = true;
            $fallback->show_order_form_alt = true;
            return $fallback;
        }
    }
}
