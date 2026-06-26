<?php

namespace App\Services\Analytics;

use Carbon\Carbon;

/**
 * Wspólne, predefiniowane zakresy dat dla dashboardów analityki
 * (lejek sprzedaży, porzucenia formularza).
 *
 * Zwraca listę presetów z gotowymi `date_from`/`date_to` (stringi YYYY-MM-DD),
 * liczonymi w biznesowej strefie czasowej. Parametr `anchorLagDays` przesuwa
 * „dzień końcowy” wstecz dla dashboardów z opóźnieniem agregacji (np. porzucenia: lag=2),
 * żeby presety domyślnie celowały w dane dojrzałe.
 */
class AnalyticsDateRangePresets
{
    /**
     * @return list<array{key: string, label: string, date_from: string, date_to: string}>
     */
    public function build(string $timezone, int $anchorLagDays = 0): array
    {
        $today = Carbon::now($timezone)->startOfDay();
        $anchor = $today->copy()->subDays(max(0, $anchorLagDays));

        $presets = [];

        foreach ([7 => 'Ostatnie 7 dni', 14 => 'Ostatnie 14 dni', 30 => 'Ostatnie 30 dni', 90 => 'Ostatnie 90 dni'] as $days => $label) {
            $presets[] = [
                'key' => $days.'d',
                'label' => $label,
                'date_from' => $anchor->copy()->subDays($days - 1)->toDateString(),
                'date_to' => $anchor->toDateString(),
            ];
        }

        // Ten miesiąc: od 1. dnia miesiąca (kotwicy) do dnia dojrzałego.
        $mtdFrom = $anchor->copy()->startOfMonth();
        $mtdTo = $anchor->copy();
        if ($mtdFrom->greaterThan($mtdTo)) {
            $mtdFrom = $mtdTo->copy();
        }
        $presets[] = [
            'key' => 'mtd',
            'label' => 'Ten miesiąc',
            'date_from' => $mtdFrom->toDateString(),
            'date_to' => $mtdTo->toDateString(),
        ];

        // Poprzedni miesiąc: pełny, w całości dojrzały.
        $prev = $today->copy()->subMonthNoOverflow();
        $presets[] = [
            'key' => 'prev_month',
            'label' => 'Poprzedni miesiąc',
            'date_from' => $prev->copy()->startOfMonth()->toDateString(),
            'date_to' => $prev->copy()->endOfMonth()->toDateString(),
        ];

        return $presets;
    }
}
