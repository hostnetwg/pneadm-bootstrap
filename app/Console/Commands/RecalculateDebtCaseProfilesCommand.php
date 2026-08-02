<?php

namespace App\Console\Commands;

use App\Models\DebtCase;
use App\Services\DebtCustomerProfileService;
use Illuminate\Console\Command;

class RecalculateDebtCaseProfilesCommand extends Command
{
    protected $signature = 'debt-cases:recalculate-profiles
                            {--apply : Zapisuje zmiany; bez tej opcji działa jako dry-run}
                            {--active-only : Przelicza tylko sprawy niezamknięte}
                            {--limit= : Maksymalna liczba spraw do sprawdzenia}';

    protected $description = 'Przelicza relationship/risk/segment/VIP dla spraw windykacyjnych według aktualnych reguł klienta';

    public function handle(DebtCustomerProfileService $profileService): int
    {
        $apply = (bool) $this->option('apply');
        $activeOnly = (bool) $this->option('active-only');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $query = DebtCase::query()
            ->with(['formOrder.primaryParticipant', 'formOrder.onlinePaymentOrders'])
            ->orderBy('id');

        if ($activeOnly) {
            $query->active();
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $checked = 0;
        $changed = 0;
        $samples = [];

        foreach ($query->get() as $case) {
            if (! $case->formOrder) {
                continue;
            }

            $checked++;
            $profile = $profileService->profileForOrder($case->formOrder);
            $newValues = [
                'customer_segment' => $profile['customer_segment'],
                'risk_score' => $profile['risk_score'],
                'relationship_score' => $profile['relationship_score'],
                'vip_reason' => $profile['vip_reason'],
            ];

            $changes = [];
            foreach ($newValues as $field => $newValue) {
                if ((string) ($case->{$field} ?? '') !== (string) ($newValue ?? '')) {
                    $changes[$field] = [
                        'old' => $case->{$field},
                        'new' => $newValue,
                    ];
                }
            }

            if ($changes === []) {
                continue;
            }

            $changed++;
            if (count($samples) < 10) {
                $changedFields = collect($changes)
                    ->map(fn (array $change, string $field) => "{$field}: ".($change['old'] ?? '—').' → '.($change['new'] ?? '—'))
                    ->values()
                    ->implode('; ');

                $samples[] = [
                    '#' => $case->id,
                    'zamówienie' => $case->form_order_id,
                    'zmiany' => $changedFields,
                    'manual_vip' => $case->manual_vip ? 'tak' : 'nie',
                ];
            }

            if ($apply) {
                $case->forceFill($newValues)->save();
            }
        }

        $this->info($apply ? 'Tryb zapisu (--apply).' : 'Dry-run: nic nie zapisano.');
        $this->line("Sprawdzono: {$checked}");
        $this->line("Do zmiany / zmieniono: {$changed}");
        $this->line('manual_vip nie jest zmieniany przez tę komendę.');

        if ($samples !== []) {
            $this->table(['#', 'Zamówienie', 'Zmiany', 'VIP ręcznie'], $samples);
        }

        return self::SUCCESS;
    }
}
