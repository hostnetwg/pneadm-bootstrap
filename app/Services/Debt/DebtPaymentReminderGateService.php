<?php

namespace App\Services\Debt;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\DebtPaymentReminderCampaign;
use App\Services\Bank\BankStatementCoverageService;
use Illuminate\Support\Facades\Schema;

class DebtPaymentReminderGateService
{
    public function __construct(
        private readonly BankStatementCoverageService $coverage,
    ) {}

    /**
     * @return array{
     *     allowed: bool,
     *     block_reason: string|null,
     *     coverage_gaps: list<array{from: string, to: string, days: int, trailing: bool}>,
     *     coverage_period: array{from: string, to: string}|null,
     *     last_import_at: string|null,
     *     pending_review_count: int,
     *     active_campaign: bool
     * }
     */
    public function evaluate(): array
    {
        $gaps = $this->coverage->detectGaps();
        $coveragePeriod = $this->coverage->mergedCoveragePeriod();
        $pendingReview = (int) BankTransaction::query()->pendingOperatorReview()->count();
        $tablesReady = Schema::hasTable('debt_payment_reminder_campaigns')
            && Schema::hasTable('debt_payment_reminder_campaign_items');
        $activeCampaign = false;
        if ($tablesReady) {
            $activeCampaign = DebtPaymentReminderCampaign::query()
                ->whereIn('status', [
                    DebtPaymentReminderCampaign::STATUS_PENDING,
                    DebtPaymentReminderCampaign::STATUS_PROCESSING,
                ])
                ->exists();
        }

        $blockReason = null;
        if (! $tablesReady) {
            $blockReason = 'Brak tabel kampanii — uruchom migrację na serwerze.';
        } elseif ($coveragePeriod === null) {
            $blockReason = 'Brak zaimportowanych wyciągów z polem „Za okres”.';
        } elseif ($gaps !== []) {
            $blockReason = 'Uzupełnij luki w okresach wyciągów.';
        } elseif ($pendingReview > 0) {
            $blockReason = 'Rozpatrz wszystkie wpływy „Do przeglądu” (w tym „Na potem”).';
        } elseif ($activeCampaign) {
            $blockReason = 'Trwa inna kampania przypomnień — poczekaj na zakończenie lub anuluj.';
        }

        $lastImport = BankStatementImport::query()->latest('id')->first();

        return [
            'allowed' => $blockReason === null,
            'block_reason' => $blockReason,
            'coverage_gaps' => $gaps,
            'coverage_period' => $coveragePeriod,
            'last_import_at' => $lastImport?->created_at
                ?->timezone(config('app.timezone'))
                ?->format('Y-m-d H:i'),
            'pending_review_count' => $pendingReview,
            'active_campaign' => $activeCampaign,
        ];
    }

    public function assertAllowed(): void
    {
        $gate = $this->evaluate();
        if ($gate['allowed']) {
            return;
        }

        abort(403, $gate['block_reason'] ?? 'Kampania niedostępna.');
    }
}
