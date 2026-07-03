<?php

namespace App\Services;

use App\Models\FormOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FormOrderLegacyCloseService
{
    public const REASON_UNLINKED_INVOICE = 'Legacy import — FV wystawiona, brak powiązania ze szkoleniem (obsłużone poza PNEADM)';

    public const REASON_ARCHIVAL_UNPROVISIONED = 'Legacy Publigo — szkolenie zakończone, uczestnik nie dodany w PNEADM';

    public function __construct(
        private readonly FormOrderOperationalStatusService $operationalStatus
    ) {}

    /**
     * FV + brak rozpoznania kursu (product_id / legacy Publigo).
     */
    public function scopeUnlinkedInvoiced(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $courseSql = $this->operationalStatus->resolveCourseIdSql($table);

        return $query
            ->whereNull("{$table}.cancelled_at")
            ->whereNull("{$table}.legacy_handled_at")
            ->whereNotNull("{$table}.invoice_number")
            ->where("{$table}.invoice_number", '!=', '')
            ->where("{$table}.invoice_number", '!=', '0')
            ->whereRaw("({$courseSql}) IS NULL");
    }

    /**
     * FV + kurs po terminie + uczestnik zamówienia bez dostępu na szkoleniu.
     */
    public function scopeArchivalInvoicedUnprovisioned(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $courseSql = $this->operationalStatus->resolveCourseIdSql($table);
        $provisioned = $this->operationalStatus->participantProvisionedExistsSql('fop_legacy', $courseSql);

        return $query
            ->whereNull("{$table}.cancelled_at")
            ->whereNull("{$table}.legacy_handled_at")
            ->whereNotNull("{$table}.invoice_number")
            ->where("{$table}.invoice_number", '!=', '')
            ->where("{$table}.invoice_number", '!=', '0')
            ->whereExists(function ($sub) use ($table, $courseSql) {
                $sub->selectRaw('1')
                    ->from('courses as c_arch')
                    ->whereRaw("c_arch.id = ({$courseSql})")
                    ->where('c_arch.end_date', '<', now());
            })
            ->whereExists(function ($sub) use ($table, $provisioned) {
                $sub->selectRaw('1')
                    ->from('form_order_participants as fop_legacy')
                    ->whereColumn('fop_legacy.form_order_id', "{$table}.id")
                    ->whereNull('fop_legacy.deleted_at')
                    ->whereRaw("TRIM(fop_legacy.participant_email) != ''")
                    ->whereRaw("NOT ({$provisioned})");
            });
    }

    /**
     * @return array{unlinked: int, archival: int, sample_unlinked: array<int, int>, sample_archival: array<int, int>}
     */
    public function previewCounts(int $sampleSize = 10): array
    {
        return [
            'unlinked' => $this->scopeUnlinkedInvoiced(FormOrder::query())->count(),
            'archival' => $this->scopeArchivalInvoicedUnprovisioned(FormOrder::query())->count(),
            'sample_unlinked' => $this->scopeUnlinkedInvoiced(FormOrder::query())
                ->orderBy('id')
                ->limit($sampleSize)
                ->pluck('id')
                ->all(),
            'sample_archival' => $this->scopeArchivalInvoicedUnprovisioned(FormOrder::query())
                ->orderBy('id')
                ->limit($sampleSize)
                ->pluck('id')
                ->all(),
        ];
    }

    /**
     * @return array{closed: int, group: string}
     */
    public function closeGroup(string $group, ?int $userId, bool $dryRun): array
    {
        $query = match ($group) {
            'unlinked' => $this->scopeUnlinkedInvoiced(FormOrder::query()),
            'archival' => $this->scopeArchivalInvoicedUnprovisioned(FormOrder::query()),
            default => throw new \InvalidArgumentException("Nieznana grupa: {$group}"),
        };

        $reason = match ($group) {
            'unlinked' => self::REASON_UNLINKED_INVOICE,
            'archival' => self::REASON_ARCHIVAL_UNPROVISIONED,
        };

        $ids = $query->orderBy('id')->pluck('id');
        if ($ids->isEmpty()) {
            return ['closed' => 0, 'group' => $group];
        }

        if ($dryRun) {
            return ['closed' => $ids->count(), 'group' => $group];
        }

        $closed = 0;
        foreach ($ids->chunk(500) as $chunk) {
            $closed += FormOrder::query()
                ->whereIn('id', $chunk->all())
                ->update([
                    'legacy_handled_at' => now(),
                    'legacy_handled_reason' => $reason,
                    'legacy_handled_by' => $userId,
                    'updated_at' => now(),
                ]);
        }

        return ['closed' => $closed, 'group' => $group];
    }
}
