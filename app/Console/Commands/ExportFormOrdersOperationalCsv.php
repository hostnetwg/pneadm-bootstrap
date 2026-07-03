<?php

namespace App\Console\Commands;

use App\Models\FormOrder;
use App\Services\FormOrderLegacyCloseService;
use App\Services\FormOrderOperationalStatusService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

class ExportFormOrdersOperationalCsv extends Command
{
    protected $signature = 'form-orders:export-handling-csv
                            {--output= : Pełna ścieżka pliku CSV (domyślnie storage/app/exports/...)}
                            {--scope=handling : handling | handling_active | legacy_unlinked | legacy_archival | legacy_both}';

    protected $description = 'Eksport CSV zamówień operacyjnych (kolejka do obsługi, legacy przed domknięciem, audyt prod)';

    public function handle(
        FormOrderOperationalStatusService $operationalStatus,
        FormOrderLegacyCloseService $legacyClose
    ): int {
        $scope = (string) $this->option('scope');
        $allowed = ['handling', 'handling_active', 'legacy_unlinked', 'legacy_archival', 'legacy_both'];

        if (! in_array($scope, $allowed, true)) {
            $this->error('Scope musi być: '.implode(', ', $allowed).'.');

            return self::FAILURE;
        }

        $query = $this->buildQuery($scope, $operationalStatus, $legacyClose);
        $outputPath = $this->resolveOutputPath($scope);
        File::ensureDirectoryExists(dirname($outputPath));

        $headers = [
            'id',
            'created_at',
            'operational_status',
            'operational_status_label',
            'course_id',
            'course_title',
            'course_end_date',
            'product_id',
            'publigo_product_id',
            'invoice_number',
            'invoice_exempt_at',
            'payment_status',
            'payment_mode',
            'status_completed',
            'cancelled_at',
            'legacy_handled_at',
            'legacy_handled_reason',
            'expected_participants',
            'provisioned_participants',
            'warnings',
            'orderer_email',
            'participant_email',
        ];

        $file = fopen($outputPath, 'w');
        if ($file === false) {
            $this->error('Nie udało się otworzyć pliku do zapisu: '.$outputPath);

            return self::FAILURE;
        }

        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($file, $headers, ',', '"');

        $count = 0;
        $query
            ->with(['primaryParticipant', 'course'])
            ->orderBy('id')
            ->chunk(300, function ($orders) use ($operationalStatus, $file, &$count) {
                foreach ($orders as $order) {
                    $ev = $operationalStatus->evaluate($order);
                    $course = $order->course;
                    if ($course === null && $ev['course_id']) {
                        $course = \App\Models\Course::query()->find($ev['course_id']);
                    }

                    fputcsv($file, [
                        $order->id,
                        $order->created_at?->format('Y-m-d H:i:s'),
                        $ev['status'],
                        $ev['label'],
                        $ev['course_id'],
                        $course?->title ?? '',
                        $course?->end_date?->format('Y-m-d H:i:s') ?? '',
                        $order->product_id,
                        $order->publigo_product_id,
                        $order->invoice_number,
                        $order->invoice_exempt_at?->format('Y-m-d H:i:s'),
                        $order->payment_status,
                        $order->payment_mode,
                        $order->status_completed,
                        $order->cancelled_at?->format('Y-m-d H:i:s'),
                        $order->legacy_handled_at?->format('Y-m-d H:i:s'),
                        $order->legacy_handled_reason,
                        $ev['expected_count'],
                        $ev['provisioned_count'],
                        implode(' | ', $ev['warnings']),
                        $order->orderer_email,
                        $order->primaryParticipant?->participant_email ?? '',
                    ], ',', '"');
                    $count++;
                }
            });

        fclose($file);

        $this->info("Eksport CSV zakończony (scope: {$scope}).");
        $this->line('Plik: '.$outputPath);
        $this->line("Liczba wierszy: {$count}");

        return self::SUCCESS;
    }

    private function buildQuery(
        string $scope,
        FormOrderOperationalStatusService $operationalStatus,
        FormOrderLegacyCloseService $legacyClose
    ): Builder {
        return match ($scope) {
            'handling' => FormOrder::query()->needsHandling(),
            'handling_active' => FormOrder::query()->needsActiveHandling(),
            'legacy_unlinked' => $legacyClose->scopeUnlinkedInvoiced(FormOrder::query()),
            'legacy_archival' => $legacyClose->scopeArchivalInvoicedUnprovisioned(FormOrder::query()),
            'legacy_both' => FormOrder::query()->where(function (Builder $q) use ($legacyClose) {
                $q->whereIn('id', $legacyClose->scopeUnlinkedInvoiced(FormOrder::query())->select('id'))
                    ->orWhereIn('id', $legacyClose->scopeArchivalInvoicedUnprovisioned(FormOrder::query())->select('id'));
            }),
        };
    }

    private function resolveOutputPath(string $scope): string
    {
        $output = trim((string) $this->option('output'));
        if ($output !== '') {
            return $output;
        }

        return storage_path('app/exports/form_orders_'.$scope.'_'.now()->format('Y_m_d_His').'.csv');
    }
}
