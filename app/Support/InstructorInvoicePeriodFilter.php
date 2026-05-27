<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

class InstructorInvoicePeriodFilter
{
    public const PERIOD_CURRENT_MONTH = 'current_month';

    public const PERIOD_PREVIOUS_MONTH = 'previous_month';

    public const PERIOD_CURRENT_YEAR = 'current_year';

    public const PERIOD_PREVIOUS_YEAR = 'previous_year';

    public const PERIOD_CUSTOM = 'custom';

    /** @return array<string, string> */
    public static function periodOptions(): array
    {
        return [
            self::PERIOD_CURRENT_MONTH => 'Bieżący miesiąc',
            self::PERIOD_PREVIOUS_MONTH => 'Poprzedni miesiąc',
            self::PERIOD_CURRENT_YEAR => 'Bieżący rok',
            self::PERIOD_PREVIOUS_YEAR => 'Poprzedni rok',
            self::PERIOD_CUSTOM => 'Własny zakres (daty od–do)',
        ];
    }

    /**
     * @return array{period: string, date_from: string|null, date_to: string|null}
     */
    public static function resolve(Request $request, ?Carbon $now = null): array
    {
        $now = $now ?? now();
        $period = $request->input('period', self::PERIOD_CURRENT_MONTH);

        if (! array_key_exists($period, self::periodOptions())) {
            $period = self::PERIOD_CURRENT_MONTH;
        }

        if ($period === self::PERIOD_CUSTOM) {
            return [
                'period' => self::PERIOD_CUSTOM,
                'date_from' => $request->filled('date_from') ? (string) $request->input('date_from') : null,
                'date_to' => $request->filled('date_to') ? (string) $request->input('date_to') : null,
            ];
        }

        [$from, $to] = self::datesForPeriod($period, $now);

        return [
            'period' => $period,
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public static function datesForPeriod(string $period, ?Carbon $now = null): array
    {
        $now = $now ?? now();

        return match ($period) {
            self::PERIOD_PREVIOUS_MONTH => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            self::PERIOD_CURRENT_YEAR => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
            ],
            self::PERIOD_PREVIOUS_YEAR => [
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
        };
    }

    /** @return array<string, string> */
    public static function defaultQueryParams(?Carbon $now = null): array
    {
        $now = $now ?? now();
        [$from, $to] = self::datesForPeriod(self::PERIOD_CURRENT_MONTH, $now);

        return [
            'period' => self::PERIOD_CURRENT_MONTH,
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
        ];
    }

    public static function shouldApplyDefaultPeriod(Request $request): bool
    {
        return ! $request->hasAny([
            'period',
            'date_from',
            'date_to',
            'instructor_id',
            'payment_status',
            'search',
            'page',
        ]);
    }
}
