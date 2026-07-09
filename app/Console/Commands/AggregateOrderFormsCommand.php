<?php

namespace App\Console\Commands;

use App\Services\Analytics\OrderFormFunnelAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class AggregateOrderFormsCommand extends Command
{
    protected $signature = 'analytics:aggregate-order-forms
        {--date= : Pojedyncza data statystyki (Y-m-d) w strefie Europe/Warsaw}
        {--from= : Początek zakresu dat (Y-m-d)}
        {--to= : Koniec zakresu dat (Y-m-d)}
        {--yesterday : Skrót dla wczoraj (Europe/Warsaw)}
        {--rebuild : Alias --force — przelicz od zera (idempotentne)}
        {--force : Wymuś ponowne przeliczenie (agregacja i tak jest idempotentna)}';

    protected $description = 'Przelicza dzienne agregaty lejka formularza (B4) z analytics_events i order_form_attributions.';

    public function handle(OrderFormFunnelAggregationService $aggregationService): int
    {
        $timezone = $aggregationService->timezone();

        if ($this->option('date') && ($this->option('from') || $this->option('to') || $this->option('yesterday'))) {
            $this->error('Użyj jednej opcji daty: --date, --from/--to lub --yesterday.');

            return self::FAILURE;
        }

        if (($this->option('from') && ! $this->option('to')) || (! $this->option('from') && $this->option('to'))) {
            $this->error('Zakres wymaga jednocześnie --from i --to.');

            return self::FAILURE;
        }

        try {
            if ($this->option('yesterday')) {
                $statDate = Carbon::now($timezone)->subDay()->startOfDay();
                $result = $aggregationService->aggregateForDate($statDate);
            } elseif ($this->option('date')) {
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
                    'Brak opcji daty — użyto domyślnie dnia z opóźnieniem dojrzałości (%s, %s).',
                    $statDate->toDateString(),
                    $timezone
                ));
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('force') || $this->option('rebuild')) {
            $this->line('Opcja rebuild/force: wyniki zostały przeliczone od zera i nadpisane.');
        }

        $this->info(sprintf(
            'Agregacja B4 formularza zakończona. Dni: %d, kanały: %d, kurs+kanał: %d, kampanie: %d, GUS: %d, jakość: %d.',
            count($result['dates']),
            $result['channel_rows'],
            $result['course_channel_rows'],
            $result['campaign_rows'],
            $result['gus_rows'],
            $result['data_quality_rows']
        ));

        $this->line('Daty: '.implode(', ', $result['dates']));

        return self::SUCCESS;
    }
}
