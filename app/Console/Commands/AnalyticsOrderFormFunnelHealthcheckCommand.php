<?php

namespace App\Console\Commands;

use App\Services\Analytics\OrderFormFunnelHealthcheckService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Walidacja jakości trackingu formularza B4+ (READ-ONLY).
 * Osobna komenda — NIE zastępuje analytics:abandonment-healthcheck.
 */
class AnalyticsOrderFormFunnelHealthcheckCommand extends Command
{
    protected $signature = 'analytics:order-form-funnel-healthcheck
        {--days=7 : Liczba ostatnich dni (gdy brak --from/--to)}
        {--from= : Początek zakresu (Y-m-d)}
        {--to= : Koniec zakresu (Y-m-d)}';

    protected $description = 'Healthcheck B4+: jakość trackingu formularza, atrybucja, eventy v2 i agregaty data quality.';

    public function handle(OrderFormFunnelHealthcheckService $healthcheck): int
    {
        $timezone = $healthcheck->timezone();
        [$from, $to] = $this->resolveRange($timezone);

        $this->info(sprintf('Healthcheck lejka formularza (B4+) — %s … %s (%s)', $from, $to, $timezone));
        $this->newLine();

        $result = $healthcheck->run($from, $to);

        if (! $result['tables_ok']) {
            $this->error('Brak wymaganych tabel: '.implode(', ', $result['missing_tables']));
            $this->line('Uruchom: sail artisan migrate --force');

            return self::FAILURE;
        }

        $this->line('1) Dopływ eventów v2 (okno czasu):');
        $this->line('   '.$result['v2_inflow']['message']);
        $this->newLine();

        $this->line('2) Agregaty jakości danych (CRITICAL/WARNING z evaluatora):');

        if ($result['daily_alerts'] === []) {
            $this->warn('   Brak wierszy analytics_daily_data_quality w zakresie — uruchom analytics:aggregate-order-forms.');
        } else {
            $this->table(
                ['Data', 'Sesje', 'Score', 'Status', 'Poziom', 'Skipped'],
                collect($result['daily_alerts'])->map(fn (array $row): array => [
                    $row['stat_date'],
                    $row['sessions_total'],
                    $row['score'],
                    $row['status'],
                    strtoupper($row['level']),
                    $row['skipped_hard_alerts'] ? 'tak' : 'nie',
                ])->all()
            );

            foreach ($result['daily_alerts'] as $row) {
                foreach ($row['critical'] as $message) {
                    $this->error("   [CRITICAL {$row['stat_date']}] {$message}");
                }
                foreach ($row['warning'] as $message) {
                    $this->warn("   [WARNING {$row['stat_date']}] {$message}");
                }
                foreach ($row['info'] as $message) {
                    $this->line("   [INFO {$row['stat_date']}] {$message}");
                }
            }
        }

        $this->newLine();

        if ($result['has_critical']) {
            $this->error('WERDYKT: CRITICAL — sprawdź tracking formularza / atrybucję / agregaty B4+.');

            return self::FAILURE;
        }

        if ($result['has_warning']) {
            $this->warn('WERDYKT: WARNING — degradacja jakości trackingu (bez CRITICAL).');

            return self::SUCCESS;
        }

        $this->info('WERDYKT: OK — brak alertów CRITICAL/WARNING (low_volume, warmup i dane sprzed atrybucji 2F pomijają twarde progi).');

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRange(string $timezone): array
    {
        if ($this->option('from') && $this->option('to')) {
            $from = Carbon::parse((string) $this->option('from'), $timezone)->startOfDay();
            $to = Carbon::parse((string) $this->option('to'), $timezone)->startOfDay();
            if ($from->greaterThan($to)) {
                [$from, $to] = [$to, $from];
            }

            return [$from->toDateString(), $to->toDateString()];
        }

        $days = max(1, (int) $this->option('days'));
        $lag = max(0, (int) config('analytics.order_form_funnel.aggregation_lag_days', 2));
        $to = Carbon::now($timezone)->subDays($lag)->startOfDay();
        $from = $to->copy()->subDays($days - 1);

        return [$from->toDateString(), $to->toDateString()];
    }
}
