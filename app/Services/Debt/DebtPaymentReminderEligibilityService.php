<?php

namespace App\Services\Debt;

use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\DebtPaymentReminderCampaign;
use App\Models\DebtPaymentReminderCampaignItem;
use App\Models\FormOrder;
use App\Services\Bank\BankTransactionMatcher;
use App\Services\DebtCaseInvoiceIdentityService;
use App\Services\DebtReminderTemplateService;
use App\Services\IfirmaInvoicePaymentStatusService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DebtPaymentReminderEligibilityService
{
    public const REASON_STATUS = 'status';

    public const REASON_NO_INVOICE = 'no_invoice';

    public const REASON_NO_REAL_DUE = 'no_real_due';

    public const REASON_NOT_OVERDUE_ENOUGH = 'not_overdue_enough';

    public const REASON_FULLY_COVERED = 'fully_covered';

    public const REASON_PARTIAL_BANK = 'partial_bank';

    public const REASON_IFIRMA_PAID = 'ifirma_paid';

    public const REASON_IFIRMA_PARTIAL = 'ifirma_partial';

    public const REASON_INVOICE_DRIFT = 'invoice_drift';

    public const REASON_DO_NOT_AUTO_DUN = 'do_not_auto_dun';

    public const REASON_CONTACTED_TODAY = 'contacted_today';

    public const REASON_COOLDOWN = 'cooldown_7d';

    public const REASON_VIP_AUDIENCE = 'vip_audience';

    public const REASON_STANDARD_AUDIENCE = 'standard_audience';

    public const REASON_NO_EMAIL = 'no_email';

    public const REASON_NO_PHONE = 'no_phone';

    public const REASON_IN_ACTIVE_CAMPAIGN = 'in_active_campaign';

    public function __construct(
        private readonly DebtCaseInvoiceIdentityService $invoiceIdentity,
        private readonly DebtReminderTemplateService $templates,
    ) {}

    /**
     * @return array<string, string>
     */
    public function exclusionLabels(): array
    {
        return [
            self::REASON_STATUS => 'Status wykluczony (obietnica / sporne / wstrzymane / zamknięte)',
            self::REASON_NO_INVOICE => 'Brak wystawionej faktury',
            self::REASON_NO_REAL_DUE => 'Brak terminu z faktury (tylko szacunek)',
            self::REASON_NOT_OVERDUE_ENOUGH => 'Za wcześnie (bulk od 2. dnia po terminie)',
            self::REASON_FULLY_COVERED => 'Pełne pokrycie przelewami',
            self::REASON_PARTIAL_BANK => 'Częściowa wpłata z wyciągu — wymaga ustalenia salda',
            self::REASON_IFIRMA_PAID => 'Status iFirma: opłacone',
            self::REASON_IFIRMA_PARTIAL => 'Status iFirma: opłacone częściowo',
            self::REASON_INVOICE_DRIFT => 'Rozbieżność FV / kwoty / tematu z zamówieniem',
            self::REASON_DO_NOT_AUTO_DUN => 'Wstrzymanie automatycznych monitów',
            self::REASON_CONTACTED_TODAY => 'Kontakt dziś (e-mail / SMS / telefon)',
            self::REASON_COOLDOWN => 'Ponowny kontakt bulk częściej niż co 7 dni',
            self::REASON_VIP_AUDIENCE => 'Klient VIP (zakładka Standard)',
            self::REASON_STANDARD_AUDIENCE => 'Klient nie-VIP (zakładka VIP)',
            self::REASON_NO_EMAIL => 'Brak e-maila zamawiającego',
            self::REASON_NO_PHONE => 'Brak telefonu zamawiającego',
            self::REASON_IN_ACTIVE_CAMPAIGN => 'Już w aktywnej kampanii',
        ];
    }

    public function exclusionLabel(string $reason): string
    {
        return $this->exclusionLabels()[$reason] ?? $reason;
    }

    /**
     * @return array{
     *     candidates: Collection<int, array<string, mixed>>,
     *     excluded: Collection<int, array{case: DebtCase, reason: string, reason_label: string}>,
     *     counts: array{candidates: int, excluded: int}
     * }
     */
    public function buildLists(
        string $audience,
        bool $requireEmail,
        bool $requirePhone,
    ): array {
        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $maxDueInclusive = $today->copy()->subDays(2);

        $cases = DebtCase::query()
            ->with([
                'formOrder',
                'bankTransactionMatches.transaction',
            ])
            ->whereIn('status', [
                DebtCase::STATUS_OPEN,
                DebtCase::STATUS_IN_PROGRESS,
                DebtCase::STATUS_PROMISED,
                DebtCase::STATUS_DISPUTED,
                DebtCase::STATUS_PAUSED,
            ])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->toDateString())
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $activeCaseIds = DebtPaymentReminderCampaignItem::query()
            ->whereIn('status', [
                DebtPaymentReminderCampaignItem::STATUS_PENDING,
                DebtPaymentReminderCampaignItem::STATUS_PROCESSING,
            ])
            ->whereHas('campaign', function ($q) {
                $q->whereIn('status', [
                    DebtPaymentReminderCampaign::STATUS_PENDING,
                    DebtPaymentReminderCampaign::STATUS_PROCESSING,
                ]);
            })
            ->pluck('debt_case_id')
            ->unique()
            ->all();

        $candidates = collect();
        $excluded = collect();

        foreach ($cases as $case) {
            $reason = $this->exclusionReason(
                $case,
                $audience,
                $requireEmail,
                $requirePhone,
                $today,
                $maxDueInclusive,
                $activeCaseIds,
            );

            if ($reason !== null) {
                $excluded->push([
                    'case' => $case,
                    'reason' => $reason,
                    'reason_label' => $this->exclusionLabel($reason),
                ]);

                continue;
            }

            $candidates->push($this->candidateRow($case, $today));
        }

        return [
            'candidates' => $candidates->values(),
            'excluded' => $excluded->values(),
            'counts' => [
                'candidates' => $candidates->count(),
                'excluded' => $excluded->count(),
            ],
        ];
    }

    /**
     * @param  list<int>  $activeCaseIds
     */
    public function exclusionReason(
        DebtCase $case,
        string $audience,
        bool $requireEmail,
        bool $requirePhone,
        ?Carbon $today = null,
        ?Carbon $maxDueInclusive = null,
        ?array $activeCaseIds = null,
    ): ?string {
        $today ??= Carbon::now(config('app.timezone'))->startOfDay();
        $maxDueInclusive ??= $today->copy()->subDays(2);

        if (! in_array($case->status, [DebtCase::STATUS_OPEN, DebtCase::STATUS_IN_PROGRESS], true)) {
            return self::REASON_STATUS;
        }

        $order = $case->formOrder;
        $hasInvoice = filled($case->invoice_number)
            || filled($case->ifirma_invoice_id)
            || filled($order?->invoice_number)
            || filled($order?->ifirma_invoice_id);
        if (! $hasInvoice) {
            return self::REASON_NO_INVOICE;
        }

        if ($order?->invoice_due_date === null) {
            return self::REASON_NO_REAL_DUE;
        }

        if ($case->due_date === null) {
            return self::REASON_NO_REAL_DUE;
        }

        $due = $case->due_date->copy()->timezone(config('app.timezone'))->startOfDay();
        if ($due->gt($maxDueInclusive)) {
            return self::REASON_NOT_OVERDUE_ENOUGH;
        }

        if ($case->isFullyCoveredByBankPayments()) {
            return self::REASON_FULLY_COVERED;
        }

        $allocated = $case->acceptedBankAllocatedSum();
        $remaining = $case->remainingBankAllocatableAmount();
        if ($allocated > BankTransactionMatcher::AMOUNT_EPSILON
            && $remaining !== null
            && $remaining > BankTransactionMatcher::AMOUNT_EPSILON
        ) {
            return self::REASON_PARTIAL_BANK;
        }

        $ifirma = $case->ifirma_payment_status;
        if ($ifirma === IfirmaInvoicePaymentStatusService::STATUS_PAID) {
            return self::REASON_IFIRMA_PAID;
        }
        if ($ifirma === IfirmaInvoicePaymentStatusService::STATUS_PARTIAL) {
            return self::REASON_IFIRMA_PARTIAL;
        }

        $drift = $this->invoiceIdentity->compare($case);
        if ($drift['can_update'] ?? false) {
            return self::REASON_INVOICE_DRIFT;
        }

        if ($case->do_not_auto_dun) {
            return self::REASON_DO_NOT_AUTO_DUN;
        }

        if ($case->reminderContactedToday()) {
            return self::REASON_CONTACTED_TODAY;
        }

        if ($this->hasRecentBulkContact($case, $today->copy()->subDays(7))) {
            return self::REASON_COOLDOWN;
        }

        $isVip = $case->isVip();
        if ($audience === DebtPaymentReminderCampaign::AUDIENCE_STANDARD && $isVip) {
            return self::REASON_VIP_AUDIENCE;
        }
        if ($audience === DebtPaymentReminderCampaign::AUDIENCE_VIP && ! $isVip) {
            return self::REASON_STANDARD_AUDIENCE;
        }

        if ($requireEmail && ! $this->hasOrdererEmail($order)) {
            return self::REASON_NO_EMAIL;
        }
        if ($requirePhone && ! $this->hasOrdererPhone($order)) {
            return self::REASON_NO_PHONE;
        }

        $activeCaseIds ??= [];
        if (in_array((int) $case->id, array_map('intval', $activeCaseIds), true)) {
            return self::REASON_IN_ACTIVE_CAMPAIGN;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function candidateRow(DebtCase $case, ?Carbon $today = null): array
    {
        $today ??= Carbon::now(config('app.timezone'))->startOfDay();
        $order = $case->formOrder;
        $due = $case->due_date?->copy()->timezone(config('app.timezone'))->startOfDay();
        $daysOverdue = $due ? (int) $due->diffInDays($today) : 0;
        $remaining = $case->remainingBankAllocatableAmount();
        if ($remaining === null) {
            $remaining = $case->invoiceTargetAmount();
        }

        $lastContact = collect([
            $case->lastReminderEmailSentAt(),
            $case->lastReminderSmsSentAt(),
            $case->lastPhoneCallLoggedAt(),
        ])->filter()->sortDesc()->first();

        return [
            'id' => $case->id,
            'invoice_number' => $case->invoice_number ?: $order?->invoice_number,
            'client' => $order?->recipient_name
                ?: $order?->buyer_name
                ?: $order?->orderer_name
                ?: '—',
            'amount_remaining' => round((float) $remaining, 2),
            'amount_remaining_formatted' => number_format((float) $remaining, 2, ',', ' ').' zł',
            'due_date' => $due?->toDateString(),
            'days_overdue' => $daysOverdue,
            'last_contact_at' => $lastContact
                ? $case->reminderLastSentLabel($lastContact)
                : '—',
            'ifirma_status' => $case->ifirmaPaymentStatusLabel(),
            'ifirma_synced_at' => $case->ifirma_synced_at
                ?->timezone(config('app.timezone'))
                ?->format('Y-m-d H:i'),
            'has_email' => $this->hasOrdererEmail($order),
            'has_phone' => $this->hasOrdererPhone($order),
            'orderer_email' => trim((string) ($order?->orderer_email ?? '')),
            'orderer_phone' => trim((string) ($order?->orderer_phone ?? '')),
            'show_url' => route('accounting.collections.show', $case),
            'is_vip' => $case->isVip(),
        ];
    }

    public function hasOrdererEmail(?FormOrder $order): bool
    {
        $email = trim((string) ($order?->orderer_email ?? ''));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function hasOrdererPhone(?FormOrder $order): bool
    {
        return $this->templates->normalizePhoneToSmsapi((string) ($order?->orderer_phone ?? '')) !== null;
    }

    private function hasRecentBulkContact(DebtCase $case, Carbon $since): bool
    {
        return DebtCaseAction::query()
            ->where('debt_case_id', $case->id)
            ->whereIn('action_type', [DebtCaseAction::TYPE_EMAIL, DebtCaseAction::TYPE_SMS])
            ->where('outcome', 'sent')
            ->where('happened_at', '>=', $since)
            ->exists();
    }
}
