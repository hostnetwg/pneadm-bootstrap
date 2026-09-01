<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Synchronizacja miesiąca księgowego iFirma z datą wystawienia faktury.
 *
 * iFirma nie przechodzi automatycznie na nowy miesiąc księgowy — wymaga ręcznej
 * zmiany w UI lub PUT abonent/miesiacksiegowy.json (NAST/POPRZ).
 *
 * @see https://api.ifirma.pl/pobranie-i-zmiana-ustawionego-miesiaca-ksiegowego/
 */
class IfirmaAccountingMonthSyncService
{
    private const MAX_STEPS = 24;

    public function __construct(
        private IfirmaApiService $api
    ) {}

    /**
     * Ustawia miesiąc księgowy iFirma tak, aby zgadzał się z miesiącem i rokiem daty docelowej.
     *
     * @return array{
     *     status: 'success'|'error'|'config_error',
     *     changed?: bool,
     *     steps?: int,
     *     from_month?: int,
     *     from_year?: int,
     *     to_month?: int,
     *     to_year?: int,
     *     message?: string
     * }
     */
    public function ensureMatchesDate(Carbon|string $targetDate): array
    {
        $target = $targetDate instanceof Carbon
            ? $targetDate->copy()
            : Carbon::parse($targetDate);

        $current = $this->api->getAccountingMonth();
        if (($current['status'] ?? '') !== 'success') {
            return [
                'status' => $current['status'] ?? 'error',
                'message' => $current['message'] ?? 'Nie udało się odczytać miesiąca księgowego iFirma.',
            ];
        }

        $fromMonth = (int) $current['month'];
        $fromYear = (int) $current['year'];
        $targetMonth = (int) $target->format('n');
        $targetYear = (int) $target->format('Y');

        $monthDiff = ($targetYear - $fromYear) * 12 + ($targetMonth - $fromMonth);

        if ($monthDiff === 0) {
            return [
                'status' => 'success',
                'changed' => false,
                'steps' => 0,
                'from_month' => $fromMonth,
                'from_year' => $fromYear,
                'to_month' => $targetMonth,
                'to_year' => $targetYear,
            ];
        }

        if (abs($monthDiff) > self::MAX_STEPS) {
            return [
                'status' => 'error',
                'message' => 'Różnica miesiąca księgowego iFirma ('.$fromMonth.'/'.$fromYear
                    .') a datą faktury ('.$targetMonth.'/'.$targetYear.') przekracza '
                    .self::MAX_STEPS.' miesięcy — wymagana ręczna korekta w panelu iFirma.',
            ];
        }

        $direction = $monthDiff > 0 ? 'NAST' : 'POPRZ';
        $steps = abs($monthDiff);
        $workingMonth = $fromMonth;
        $workingYear = $fromYear;

        Log::info('iFirma: synchronizacja miesiąca księgowego przed wystawieniem FV', [
            'from_month' => $fromMonth,
            'from_year' => $fromYear,
            'target_month' => $targetMonth,
            'target_year' => $targetYear,
            'direction' => $direction,
            'steps' => $steps,
        ]);

        for ($i = 0; $i < $steps; $i++) {
            $carryFromPreviousYear = $direction === 'NAST'
                ? ($workingMonth === 12)
                : ($workingMonth === 1);

            $change = $this->api->changeAccountingMonth($direction, $carryFromPreviousYear);
            if (($change['status'] ?? '') !== 'success') {
                Log::error('iFirma: błąd synchronizacji miesiąca księgowego', [
                    'step' => $i + 1,
                    'direction' => $direction,
                    'carry_from_previous_year' => $carryFromPreviousYear,
                    'result' => $change,
                ]);

                return [
                    'status' => 'error',
                    'message' => $change['message'] ?? 'Nie udało się zmienić miesiąca księgowego w iFirma.',
                ];
            }

            if ($direction === 'NAST') {
                if ($workingMonth === 12) {
                    $workingMonth = 1;
                    $workingYear++;
                } else {
                    $workingMonth++;
                }
            } else {
                if ($workingMonth === 1) {
                    $workingMonth = 12;
                    $workingYear--;
                } else {
                    $workingMonth--;
                }
            }
        }

        $verify = $this->api->getAccountingMonth();
        if (($verify['status'] ?? '') === 'success'
            && (int) $verify['month'] === $targetMonth
            && (int) $verify['year'] === $targetYear) {
            Log::info('iFirma: miesiąc księgowy zsynchronizowany', [
                'steps' => $steps,
                'month' => $targetMonth,
                'year' => $targetYear,
            ]);

            return [
                'status' => 'success',
                'changed' => true,
                'steps' => $steps,
                'from_month' => $fromMonth,
                'from_year' => $fromYear,
                'to_month' => $targetMonth,
                'to_year' => $targetYear,
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Po synchronizacji miesiąc księgowy iFirma nadal nie zgadza się z datą faktury.',
        ];
    }
}
