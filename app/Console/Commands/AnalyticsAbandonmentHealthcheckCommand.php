<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsAbandonmentAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Walidacja zdrowia danych porzuceń (B2 tracking + B3 agregacja). READ-ONLY.
 *
 * Sprawdza:
 *  1. Czy eventy lejka (w tym JS z B2) docierają.
 *  2. Spójność kubełków B3 (sessions_total == suma kubełków terminalnych).
 *  3. Kształt lejka + „ciemną strefę" (sesje bez startu JS) i niedoszacowanie JS.
 *
 * Nic nie zapisuje. Kod wyjścia FAILURE, gdy wykryto niespójność kubełków.
 */
class AnalyticsAbandonmentHealthcheckCommand extends Command
{
    protected $signature = 'analytics:abandonment-healthcheck
        {--days=7 : Liczba ostatnich dni do sprawdzenia (gdy brak --from/--to)}
        {--from= : Początek zakresu (Y-m-d)}
        {--to= : Koniec zakresu (Y-m-d)}';

    protected $description = 'Walidacja danych porzuceń: dopływ eventów (B2), spójność agregatów (B3) i kształt lejka.';

    private const FUNNEL_EVENTS = [
        'order_form_viewed',
        'order_form_started',
        'order_form_submit_clicked',
        'order_form_submit_attempted',
        'form_order_created',
    ];

    /** Eventy zależne od JS (B2) — używane do oceny „ciemnej strefy". */
    private const JS_EVENTS = ['order_form_started', 'order_form_submit_clicked'];

    public function handle(AnalyticsAbandonmentAggregationService $aggregation): int
    {
        $timezone = $aggregation->timezone();
        [$from, $to] = $this->resolveRange($timezone);

        $this->info(sprintf('Healthcheck porzuceń — zakres %s … %s (%s)', $from, $to, $timezone));
        $this->newLine();

        $connection = DB::connection('analytics');

        $this->checkEventInflow($connection, $from, $to);
        $this->newLine();

        $consistent = $this->checkBucketConsistency($connection, $from, $to);
        $this->newLine();

        $this->checkFunnelShape($connection, $from, $to);
        $this->newLine();

        if (! $consistent) {
            $this->error('WERDYKT: wykryto niespójność kubełków B3 (sessions_total != suma kubełków). Sprawdź agregację.');

            return self::FAILURE;
        }

        $this->info('WERDYKT: agregaty B3 spójne. Oceń tabele JS/ciemnej strefy powyżej (objaśnienie pod spodem).');
        $this->line('Wskazówka: reached_started/submit_clicked pochodzą z JS (B2) i mogą być niższe niż backendowe');
        $this->line('proba/zamówienia — duży rozjazd oznacza adblock/brak JS lub sesje sprzed wdrożenia B2.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRange(string $timezone): array
    {
        if ($this->option('from') || $this->option('to')) {
            if (! $this->option('from') || ! $this->option('to')) {
                $this->warn('Podano tylko jedną granicę zakresu — używam --days zamiast tego.');
            } else {
                $from = Carbon::parse((string) $this->option('from'), $timezone)->startOfDay();
                $to = Carbon::parse((string) $this->option('to'), $timezone)->startOfDay();
                if ($from->greaterThan($to)) {
                    [$from, $to] = [$to, $from];
                }

                return [$from->toDateString(), $to->toDateString()];
            }
        }

        $days = max(1, (int) $this->option('days'));
        $to = Carbon::now($timezone)->startOfDay();
        $from = $to->copy()->subDays($days - 1);

        return [$from->toDateString(), $to->toDateString()];
    }

    private function checkEventInflow(\Illuminate\Database\Connection $connection, string $from, string $to): void
    {
        $this->line('1) Dopływ eventów lejka (w tym JS z B2):');

        $rows = $connection->table('analytics_events')
            ->selectRaw('DATE(occurred_at) as dzien, event_name, COUNT(*) as ile')
            ->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->whereIn('event_name', self::FUNNEL_EVENTS)
            ->groupBy('dzien', 'event_name')
            ->orderBy('dzien', 'desc')
            ->orderBy('event_name')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('   Brak eventów lejka w zakresie.');

            return;
        }

        $this->table(
            ['Dzień', 'Event', 'Liczba', 'Źródło'],
            $rows->map(fn ($r): array => [
                $r->dzien,
                $r->event_name,
                (int) $r->ile,
                in_array($r->event_name, self::JS_EVENTS, true) ? 'JS (B2)' : 'backend',
            ])->all()
        );
    }

    private function checkBucketConsistency(\Illuminate\Database\Connection $connection, string $from, string $to): bool
    {
        $this->line('2) Spójność kubełków B3 (różnica musi być 0):');

        $rows = $connection->table('analytics_daily_form_abandonment_stats')
            ->selectRaw('stat_date')
            ->selectRaw('SUM(sessions_total) as sesje')
            ->selectRaw('SUM(viewed_not_started + started_not_submit_clicked + submit_clicked_not_attempted + submit_attempted_not_created + converted) as kubelki')
            ->whereBetween('stat_date', [$from, $to])
            ->groupBy('stat_date')
            ->orderBy('stat_date', 'desc')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('   Brak agregatów B3 w zakresie (możliwe, że cron jeszcze nie policzył lub brak ruchu).');

            return true;
        }

        $consistent = true;
        $this->table(
            ['Data', 'Sesje', 'Suma kubełków', 'Różnica'],
            $rows->map(function ($r) use (&$consistent): array {
                $diff = (int) $r->sesje - (int) $r->kubelki;
                if ($diff !== 0) {
                    $consistent = false;
                }

                return [$r->stat_date, (int) $r->sesje, (int) $r->kubelki, $diff];
            })->all()
        );

        return $consistent;
    }

    private function checkFunnelShape(\Illuminate\Database\Connection $connection, string $from, string $to): void
    {
        $this->line('3) Kształt lejka + ciemna strefa:');

        $rows = $connection->table('analytics_daily_form_abandonment_stats')
            ->selectRaw('stat_date')
            ->selectRaw('SUM(sessions_total) as ses')
            ->selectRaw('SUM(reached_started) as st')
            ->selectRaw('SUM(reached_submit_clicked) as sc')
            ->selectRaw('SUM(reached_submit_attempted) as sa')
            ->selectRaw('SUM(reached_created) as cr')
            ->selectRaw('ROUND(100 * SUM(viewed_not_started) / NULLIF(SUM(sessions_total), 0), 1) as pvns')
            ->whereBetween('stat_date', [$from, $to])
            ->groupBy('stat_date')
            ->orderBy('stat_date', 'desc')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('   Brak agregatów B3 w zakresie.');

            return;
        }

        $this->table(
            ['Data', 'Sesje', 'Start JS', 'Klik JS', 'Próba (BE)', 'Zam. (BE)', '% bez startu'],
            $rows->map(fn ($r): array => [
                $r->stat_date,
                (int) $r->ses,
                (int) $r->st,
                (int) $r->sc,
                (int) $r->sa,
                (int) $r->cr,
                ($r->pvns ?? '—').'%',
            ])->all()
        );
    }
}
