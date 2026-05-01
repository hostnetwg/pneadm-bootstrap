<?php

namespace App\Console\Commands;

use App\Models\FormOrder;
use App\Services\FormOrderSendySyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportFormOrdersToSendyCsv extends Command
{
    protected $signature = 'form-orders:export-sendy-csv
        {--output= : Pełna ścieżka pliku CSV (domyślnie storage/app/exports/...)}
        {--only-paid=1 : Eksportuj tylko zamówienia kursów płatnych (1/0)}';

    protected $description = 'Eksportuje uczestnika i zamawiającego z form_orders do CSV zgodnego z importem Sendy.';

    public function handle(FormOrderSendySyncService $sendySync): int
    {
        $onlyPaid = (string) $this->option('only-paid') !== '0';
        $outputPath = $this->resolveOutputPath();
        File::ensureDirectoryExists(dirname($outputPath));

        $query = FormOrder::query()
            ->with('primaryParticipant', 'course')
            ->whereNull('deleted_at');

        if ($onlyPaid) {
            $query->whereHas('course', fn ($q) => $q->where('is_paid', 1));
        }

        $orders = $query->orderBy('id')->get();
        if ($orders->isEmpty()) {
            $this->warn('Brak zamówień do eksportu.');
            return self::SUCCESS;
        }

        $rows = collect();
        $rows->push(['Name', 'Email', 'Sername', 'data', 'id_szkolenia']);

        foreach ($orders as $order) {
            foreach ($sendySync->contactsForOrder($order) as $contact) {
                $rows->push([
                    $contact['name'],
                    $contact['email'],
                    $contact['sername'],
                    $contact['data'],
                    $contact['id_szkolenia'],
                ]);
            }
        }

        $rows = $rows
            ->slice(1)
            ->unique(fn (array $row) => strtolower((string) $row[1]).'|'.(string) $row[4])
            ->prepend(['Name', 'Email', 'Sername', 'data', 'id_szkolenia'])
            ->values();

        $file = fopen($outputPath, 'w');
        if ($file === false) {
            $this->error('Nie udało się otworzyć pliku do zapisu: '.$outputPath);
            return self::FAILURE;
        }

        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        foreach ($rows as $row) {
            fputcsv($file, $row, ',', '"');
        }
        fclose($file);

        $this->info('Eksport Sendy CSV zakończony.');
        $this->line('Plik: '.$outputPath);
        $this->line('Liczba kontaktów: '.max($rows->count() - 1, 0));

        return self::SUCCESS;
    }

    private function resolveOutputPath(): string
    {
        $output = trim((string) $this->option('output'));
        if ($output !== '') {
            return $output;
        }

        return storage_path('app/exports/sendy_form_orders_'.now()->format('Y_m_d_His').'.csv');
    }
}
