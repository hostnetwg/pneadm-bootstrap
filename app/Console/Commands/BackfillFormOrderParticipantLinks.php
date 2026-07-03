<?php

namespace App\Console\Commands;

use App\Models\FormOrderParticipant;
use App\Models\Participant;
use App\Services\FormOrderOperationalStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class BackfillFormOrderParticipantLinks extends Command
{
    protected $signature = 'form-orders:backfill-participant-links
                            {--batch=500 : Liczba wierszy form_order_participants w batchu}
                            {--dry-run : Tylko raport, bez zapisu participant_id}
                            {--order-id= : Ogranicz do jednego form_orders.id}';

    protected $description = 'Uzupełnia form_order_participants.participant_id na podstawie dopasowania e-mail + course_id (fallback legacy)';

    public function handle(FormOrderOperationalStatusService $operationalStatus): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $batchSize = max(1, (int) $this->option('batch'));
        $orderId = $this->option('order-id') !== null && $this->option('order-id') !== ''
            ? (int) $this->option('order-id')
            : null;

        if ($dryRun) {
            $this->warn('Tryb dry-run — participant_id nie zostanie zapisane.');
        }

        $query = FormOrderParticipant::query()
            ->whereNull('participant_id')
            ->whereNull('deleted_at')
            ->whereRaw("TRIM(participant_email) != ''")
            ->with(['formOrder' => fn ($q) => $q->withTrashed()])
            ->when($orderId !== null, fn ($q) => $q->where('form_order_id', $orderId));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Brak wierszy do uzupełnienia (participant_id puste, e-mail obecny).');

            return self::SUCCESS;
        }

        $this->info("Do analizy: {$total} wierszy form_order_participants.");

        $linked = 0;
        $skippedNoCourse = 0;
        $skippedNoMatch = 0;
        $skippedAmbiguous = 0;
        $skippedOrderMissing = 0;

        $query->orderBy('id')->chunkById($batchSize, function (Collection $rows) use (
            $operationalStatus,
            $dryRun,
            &$linked,
            &$skippedNoCourse,
            &$skippedNoMatch,
            &$skippedAmbiguous,
            &$skippedOrderMissing
        ) {
            foreach ($rows as $fop) {
                $order = $fop->formOrder;
                if (! $order || $order->trashed()) {
                    $skippedOrderMissing++;

                    continue;
                }

                $courseId = $operationalStatus->resolveCourseId($order);
                if ($courseId === null) {
                    $skippedNoCourse++;
                    $this->line("  [brak kursu] fop #{$fop->id}, zamówienie #{$fop->form_order_id}");

                    continue;
                }

                $email = strtolower(trim((string) $fop->participant_email));
                $matches = Participant::query()
                    ->where('course_id', $courseId)
                    ->where(function ($q) use ($email) {
                        $q->where('email_normalized', $email)
                            ->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
                    })
                    ->orderBy('id')
                    ->get();

                if ($matches->isEmpty()) {
                    $skippedNoMatch++;

                    continue;
                }

                if ($matches->count() > 1) {
                    $skippedAmbiguous++;
                    $this->warn("  [wieloznaczne] fop #{$fop->id}, kurs {$courseId}, e-mail {$email} — dopasowań: {$matches->count()}");

                    continue;
                }

                $participant = $matches->first();
                if ($dryRun) {
                    $this->line("  [dry-run] fop #{$fop->id} → participants #{$participant->id} (zam. #{$fop->form_order_id})");
                } else {
                    $fop->participant_id = $participant->id;
                    $fop->save();
                }
                $linked++;
            }
        });

        $this->newLine();
        $this->info('Podsumowanie:');
        $this->table(
            ['Metryka', 'Liczba'],
            [
                ['Powiązano (lub do powiązania w dry-run)', $linked],
                ['Brak dopasowania uczestnika', $skippedNoMatch],
                ['Wieloznaczne dopasowanie (>1)', $skippedAmbiguous],
                ['Brak course_id dla zamówienia', $skippedNoCourse],
                ['Brak / usunięte zamówienie', $skippedOrderMissing],
            ]
        );

        if ($dryRun && $linked > 0) {
            $this->warn('Uruchom bez --dry-run, aby zapisać participant_id.');
        }

        return self::SUCCESS;
    }
}
