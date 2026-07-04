<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Pomocnik dla pól TIMESTAMP/DATETIME przechowywanych w UTC (np. form_orders.order_date).
 * Granice dnia liczone w strefie aplikacji, porównania w SQL jako UTC.
 */
final class UtcStorageDate
{
    public static function appTimezone(): string
    {
        return (string) config('app.timezone', 'Europe/Warsaw');
    }

    /**
     * Początek dnia kalendarzowego w strefie aplikacji → UTC (do SQL >=).
     */
    public static function dayStartUtc(CarbonInterface|string $date): Carbon
    {
        return Carbon::parse($date, self::appTimezone())->startOfDay()->utc();
    }

    /**
     * Koniec dnia kalendarzowego w strefie aplikacji → UTC (do SQL <=).
     */
    public static function dayEndUtc(CarbonInterface|string $date): Carbon
    {
        return Carbon::parse($date, self::appTimezone())->endOfDay()->utc();
    }

    /**
     * Zakres [from, to] dla whereBetween na kolumnie UTC.
     *
     * @return array{0: string, 1: string}
     */
    public static function utcRangeForLocalDays(CarbonInterface $from, CarbonInterface $to): array
    {
        $tz = self::appTimezone();

        return [
            Carbon::parse($from, $tz)->startOfDay()->utc()->format('Y-m-d H:i:s'),
            Carbon::parse($to, $tz)->endOfDay()->utc()->format('Y-m-d H:i:s'),
        ];
    }
}
