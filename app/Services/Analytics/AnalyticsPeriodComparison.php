<?php

namespace App\Services\Analytics;

use Carbon\Carbon;

/**
 * Porównanie bieżącego zakresu dat z poprzednim okresem o tej samej długości.
 *
 * Poprzedni okres kończy się dzień przed date_from bieżącego zakresu.
 */
class AnalyticsPeriodComparison
{
    /**
     * @return array{date_from: string, date_to: string, days: int}
     */
    public function previousPeriodDates(string $dateFrom, string $dateTo): array
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $days = (int) $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1);

        return [
            'date_from' => $previousFrom->toDateString(),
            'date_to' => $previousTo->toDateString(),
            'days' => $days,
        ];
    }

    /**
     * @param  array<string, int|float|null>  $current
     * @param  array<string, int|float|null>  $previous
     * @param  list<string>  $countKeys
     * @param  list<string>  $rateKeys  Wartości procentowe — delta w punktach procentowych (pp).
     * @return array{previous_period: array{date_from: string, date_to: string, days: int}, metrics: array<string, array<string, int|float|null>>}
     */
    public function build(
        string $dateFrom,
        string $dateTo,
        array $current,
        array $previous,
        array $countKeys,
        array $rateKeys = [],
    ): array {
        $period = $this->previousPeriodDates($dateFrom, $dateTo);
        $metrics = [];

        foreach ($countKeys as $key) {
            $metrics[$key] = $this->compareCount(
                (float) ($current[$key] ?? 0),
                (float) ($previous[$key] ?? 0),
            );
        }

        foreach ($rateKeys as $key) {
            $currentRate = isset($current[$key]) && $current[$key] !== null ? (float) $current[$key] : null;
            $previousRate = isset($previous[$key]) && $previous[$key] !== null ? (float) $previous[$key] : null;
            $metrics[$key] = $this->compareRate($currentRate, $previousRate);
        }

        return [
            'previous_period' => $period,
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{current: float, previous: float, delta: float, delta_percent: float|null}
     */
    private function compareCount(float $current, float $previous): array
    {
        $delta = $current - $previous;

        return [
            'current' => $current,
            'previous' => $previous,
            'delta' => $delta,
            'delta_percent' => $previous > 0
                ? round(($delta / $previous) * 100, 1)
                : ($current > 0 ? null : 0.0),
        ];
    }

    /**
     * @return array{current: float|null, previous: float|null, delta: float|null, delta_percent: null}
     */
    private function compareRate(?float $current, ?float $previous): array
    {
        $delta = ($current !== null && $previous !== null)
            ? round($current - $previous, 2)
            : null;

        return [
            'current' => $current,
            'previous' => $previous,
            'delta' => $delta,
            'delta_percent' => null,
        ];
    }
}
