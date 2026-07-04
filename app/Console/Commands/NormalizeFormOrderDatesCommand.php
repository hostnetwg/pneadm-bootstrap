<?php

namespace App\Console\Commands;

use App\Models\FormOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class NormalizeFormOrderDatesCommand extends Command
{
    protected $signature = 'form-orders:normalize-order-dates
                            {--dry-run : Tylko raport (i opcjonalnie CSV), bez UPDATE}
                            {--scope=pnedu_bug : pnedu_bug — zamówienia z pnedu.pl z błędem −2h w zapisie}
                            {--since=2025-10-18 : Dolna granica order_date dla scope pnedu_bug}
                            {--order-id= : Korekta pojedynczego zamówienia (np. 7423)}
                            {--export-csv : Zapisz pełny raport CSV do storage/app/exports/}
                            {--output= : Ścieżka CSV (wymaga --export-csv)}
                            {--before= : Górna granica order_date (UTC, Y-m-d H:i:s) — tylko zamówienia zapisane przed wdrożeniem fixu}
                            {--exclude-ids= : ID do pominięcia (po przecinku, np. 7423)}
                            {--force : Bez pytania o potwierdzenie}';

    protected $description = 'Korekta historycznych order_date (UTC) — kohorta pnedu_order_form (+2h w bazie)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $scope = (string) $this->option('scope');
        $since = (string) $this->option('since');
        $before = $this->option('before');
        $orderId = $this->option('order-id');
        $excludeIds = $this->parseExcludeIds($this->option('exclude-ids'));

        if ($orderId !== null && $orderId !== '') {
            return $this->normalizeSingle((int) $orderId, $dryRun);
        }

        if ($scope !== 'pnedu_bug') {
            $this->error('Obsługiwany jest wyłącznie scope=pnedu_bug (zamówienia z formularza pnedu.pl).');
            $this->line('Rekordy legacy (submission_source NULL) — poza zakresem tej komendy; certgen nie jest już źródłem prawdy.');

            return self::FAILURE;
        }

        $query = $this->pneduBugQuery($since, $before, $excludeIds);
        $hours = 2;
        $count = (clone $query)->count();

        $this->info("Zakres: submission_source=pnedu_order_form, order_date >= {$since}");
        if (is_string($before) && $before !== '') {
            $this->info("         order_date <= {$before} (tylko sprzed wdrożenia fixu kodu)");
        }
        if ($excludeIds !== []) {
            $this->info('         wykluczone ID: '.implode(', ', $excludeIds));
        }
        $this->info("Korekta: DATE_ADD(order_date, INTERVAL +{$hours} HOUR) — naprawa błędu zapisu przy DB_TIMEZONE=+02:00");
        $this->info("Rekordów: {$count}");

        if ($count === 0) {
            $this->warn('Brak rekordów do korekty.');

            return self::SUCCESS;
        }

        $this->line('Przykładowe 5 (przed → po):');
        foreach ((clone $query)->orderByDesc('id')->limit(5)->get() as $order) {
            $this->line('  '.$this->formatSampleLine($order, $hours));
        }

        if ($this->option('export-csv')) {
            $path = $this->exportCsv($query, $hours);
            $this->info("CSV: {$path}");
        }

        if ($dryRun) {
            $this->warn('Dry-run — brak zmian w bazie.');
            $this->line('Uwaga: po jednorazowej korekcie (--force) ten sam dry-run nadal pokaże rekordy z kohorty.');
            $this->line('         Nie uruchamiaj --force ponownie z tymi samymi parametrami (podwójna korekta +2h).');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Zaktualizować {$count} rekordów?", false)) {
            $this->info('Anulowano.');

            return self::SUCCESS;
        }

        $updated = DB::table('form_orders')
            ->whereIn('id', (clone $query)->pluck('id'))
            ->update([
                'order_date' => DB::raw('DATE_ADD(order_date, INTERVAL 2 HOUR)'),
            ]);

        $this->info("Zaktualizowano: {$updated}");

        return self::SUCCESS;
    }

    private function pneduBugQuery(string $since, mixed $before, array $excludeIds): Builder
    {
        $query = FormOrder::query()
            ->whereNotNull('order_date')
            ->where('submission_source', FormOrder::SUBMISSION_SOURCE_PNEDU_ORDER_FORM)
            ->where('order_date', '>=', $since);

        if (is_string($before) && $before !== '') {
            $query->where('order_date', '<=', $before);
        }

        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    private function parseExcludeIds(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $part): int => (int) trim($part),
            explode(',', $value)
        )));
    }

    private function formatSampleLine(FormOrder $order, int $hours): string
    {
        $raw = $order->getRawOriginal('order_date');
        $after = DB::selectOne('SELECT DATE_ADD(?, INTERVAL ? HOUR) as d', [$raw, $hours])->d;
        $afterUi = Carbon::parse($after, 'UTC')->timezone(config('app.timezone'))->format('d.m.Y H:i');

        return "#{$order->id} {$order->ident}: {$raw} → {$after} (UI: {$order->formatOrderDateLocal()} → {$afterUi})";
    }

    private function exportCsv(Builder $query, int $hours): string
    {
        $output = $this->option('output');
        $path = is_string($output) && $output !== ''
            ? $output
            : storage_path('app/exports/form_orders_order_date_pnedu_bug_'.now()->format('Y-m-d_His').'.csv');

        File::ensureDirectoryExists(dirname($path));

        $file = fopen($path, 'w');
        if ($file === false) {
            throw new \RuntimeException('Nie udało się otworzyć pliku: '.$path);
        }

        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($file, [
            'id',
            'ident',
            'submission_source',
            'order_date_utc_before',
            'display_pl_before',
            'order_date_utc_after',
            'display_pl_after',
            'correction_hours',
            'product_name',
            'orderer_email',
            'invoice_number',
        ], ',', '"');

        (clone $query)->orderBy('id')->chunk(500, function ($orders) use ($file, $hours) {
            foreach ($orders as $order) {
                $raw = $order->getRawOriginal('order_date');
                $after = DB::selectOne('SELECT DATE_ADD(?, INTERVAL ? HOUR) as d', [$raw, $hours])->d;
                $afterUi = Carbon::parse($after, 'UTC')->timezone(config('app.timezone'))->format('d.m.Y H:i');

                fputcsv($file, [
                    $order->id,
                    $order->ident,
                    $order->submission_source,
                    $raw,
                    $order->formatOrderDateLocal('Y-m-d H:i:s'),
                    Carbon::parse($after, 'UTC')->format('Y-m-d H:i:s'),
                    $afterUi,
                    $hours,
                    $order->product_name,
                    $order->orderer_email,
                    $order->invoice_number,
                ], ',', '"');
            }
        });

        fclose($file);

        return $path;
    }

    private function normalizeSingle(int $orderId, bool $dryRun): int
    {
        $order = FormOrder::find($orderId);
        if (! $order) {
            $this->error("Brak zamówienia #{$orderId}");

            return self::FAILURE;
        }

        $raw = $order->getRawOriginal('order_date');
        $this->line("#{$orderId} ident={$order->ident} source=".($order->submission_source ?? 'NULL'));
        $this->line("  DB (UTC): {$raw}");
        $this->line("  Wyświetlane: {$order->formatOrderDateLocal()}");

        if ($order->submission_source !== FormOrder::SUBMISSION_SOURCE_PNEDU_ORDER_FORM) {
            $this->warn('  Korekta +2h dotyczy tylko submission_source=pnedu_order_form.');

            return self::SUCCESS;
        }

        $hours = 2;
        $afterRaw = DB::selectOne('SELECT DATE_ADD(?, INTERVAL ? HOUR) as d', [$raw, $hours])->d;
        $afterDisplay = Carbon::parse($afterRaw, 'UTC')
            ->timezone(config('app.timezone'))
            ->format('d.m.Y H:i');
        $this->line("  Po korekcie (+{$hours}h UTC): {$afterRaw} → UI: {$afterDisplay}");

        if ($dryRun) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Zastosować korektę?', true)) {
            return self::SUCCESS;
        }

        DB::table('form_orders')->where('id', $orderId)->update([
            'order_date' => DB::raw('DATE_ADD(order_date, INTERVAL 2 HOUR)'),
        ]);

        $this->info('Zaktualizowano.');

        return self::SUCCESS;
    }
}
