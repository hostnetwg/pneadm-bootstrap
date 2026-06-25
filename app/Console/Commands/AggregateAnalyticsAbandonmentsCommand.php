<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsAbandonmentAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class AggregateAnalyticsAbandonmentsCommand extends Command
{
    protected $signature = 'analytics:aggregate-abandonments
        {--date= : Pojedyncza data statystyki (Y-m-d) w strefie Europe/Warsaw}
        {--from= : Początek zakresu dat (Y-m-d) w strefie Europe/Warsaw}
        {--to= : Koniec zakresu dat (Y-m-d) w strefie Europe/Warsaw}
        {--force : Wymuś ponowne przeliczenie (agregacja i tak jest idempotentna)}';

    protected $description = 'Przelicza dzienne agregaty porzuceń formularza z analytics_events do analytics_daily_*_abandonment_stats.';

    public function handle(AnalyticsAbandonmentAggregationService $aggregationService): int
    {
        $timezone = $aggregationService->timezone();

        if ($this->option('date') && ($this->option('from') || $this->option('to'))) {
            $this->error('Użyj albo --date, albo pary --from i --to, nie obu jednocześnie.');

            return self::FAILURE;
        }

        if (($this->option('from') && ! $this->option('to')) || (! $this->option('from') && $this->option('to'))) {
            $this->error('Zakres wymaga jednocześnie --from i --to.');

            return self::FAILURE;
        }

        try {
            if ($this->option('date')) {
                $statDate = Carbon::parse((string) $this->option('date'), $timezone)->startOfDay();
                $result = $aggregationService->aggregateForDate($statDate);
            } elseif ($this->option('from') && $this->option('to')) {
                $from = Carbon::parse((string) $this->option('from'), $timezone)->startOfDay();
                $to = Carbon::parse((string) $this->option('to'), $timezone)->startOfDay();
                $result = $aggregationService->aggregateForDateRange($from, $to);
            } else {
                $statDate = $aggregationService->defaultStatDate();
                $result = $aggregationService->aggregateForDate($statDate);
                $this->line(sprintf(
                    'Brak opcji daty — użyto domyślnie dnia z opóźnieniem dojrzałości sesji (%s, %s).',
                    $statDate->toDateString(),
                    $timezone
                ));
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('force')) {
            $this->line('Opcja --force: wyniki zostały przeliczone od zera i nadpisane.');
        }

        $this->info(sprintf(
            'Agregacja porzuceń zakończona. Dni: %d, wiersze kursów: %d, wiersze kampanii: %d.',
            count($result['dates']),
            $result['course_rows'],
            $result['campaign_rows']
        ));

        $this->line('Daty: '.implode(', ', $result['dates']));

        return self::SUCCESS;
    }
}
