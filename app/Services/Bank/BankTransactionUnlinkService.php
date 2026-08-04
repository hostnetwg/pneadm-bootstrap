<?php

namespace App\Services\Bank;

use App\Models\BankStatementImport;
use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\User;
use App\Services\IfirmaInvoicePaymentRegistrationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cofnięcie zaakceptowanego powiązania przelewu ze sprawą (+ best-effort iFirma).
 */
class BankTransactionUnlinkService
{
    public function __construct(
        private readonly IfirmaInvoicePaymentRegistrationService $paymentRegistration,
        private readonly BankTransactionMatcher $matcher = new BankTransactionMatcher,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     warning?: string,
     *     match: BankTransactionMatch,
     *     debt_case_id?: int|null,
     *     case_reopened: bool,
     *     ifirma_removed: bool,
     *     ifirma_attempted: bool
     * }
     */
    public function unlinkAcceptedMatch(BankTransactionMatch $match, ?User $user = null): array
    {
        $match = $match->fresh(['transaction', 'debtCase.formOrder', 'formOrder']) ?? $match;
        $match->loadMissing(['transaction', 'debtCase.formOrder', 'formOrder']);

        if ($match->status !== BankTransactionMatch::STATUS_ACCEPTED) {
            throw new InvalidArgumentException('Można cofnąć tylko zaakceptowane powiązanie przelewu.');
        }

        $transaction = $match->transaction;
        if (! $transaction) {
            throw new InvalidArgumentException('Brak transakcji bankowej dla tego dopasowania.');
        }

        $debtCase = $match->debtCase;
        $amount = round((float) $transaction->amount, 2);
        $paymentDate = $transaction->operation_date?->format('Y-m-d');

        $ifirmaResult = [
            'success' => false,
            'attempted' => false,
            'message' => 'Pominięto iFirma (brak sprawy).',
        ];
        if ($debtCase) {
            $ifirmaResult = $this->paymentRegistration->attemptRemovePaymentForDebtCase(
                $debtCase,
                $amount,
                $paymentDate,
                $user
            );
        }

        $caseReopened = false;
        $debtCaseId = $debtCase?->id;

        DB::transaction(function () use ($match, $debtCase, $transaction, $user, $amount, $paymentDate, $ifirmaResult, &$caseReopened) {
            $match->forceFill([
                'status' => BankTransactionMatch::STATUS_REJECTED,
                'accepted_by' => null,
                'accepted_at' => null,
            ])->save();

            if (! $debtCase) {
                return;
            }

            $debtCase = $debtCase->fresh() ?? $debtCase;
            $wasClosed = $debtCase->status === DebtCase::STATUS_CLOSED;

            $noteParts = [
                sprintf(
                    'Cofnięto przypisanie przelewu z wyciągu: %s PLN%s (transakcja #%d, match #%d).',
                    number_format($amount, 2, ',', ' '),
                    $paymentDate ? ' z dnia '.$paymentDate : '',
                    $transaction->id,
                    $match->id
                ),
            ];
            if ($ifirmaResult['attempted'] ?? false) {
                $noteParts[] = ($ifirmaResult['success'] ?? false)
                    ? 'Próba usunięcia wpłaty w iFirma: OK.'
                    : 'Próba usunięcia wpłaty w iFirma nie powiodła się — wymagana ręczna korekta w iFirma.';
            } elseif (($ifirmaResult['message'] ?? '') !== '') {
                $noteParts[] = $ifirmaResult['message'];
            }
            if ($wasClosed) {
                $noteParts[] = 'Sprawę otwarto ponownie.';
            }

            $debtCase->actions()->create([
                'user_id' => $user?->id,
                'action_type' => DebtCaseAction::TYPE_BANK_UNMATCH,
                'channel' => 'system',
                'happened_at' => now(),
                'note' => implode(' ', $noteParts),
            ]);

            $updates = [
                'last_action_at' => now(),
                'assigned_to_id' => $user?->id ?: $debtCase->assigned_to_id,
            ];

            if ($wasClosed) {
                $updates['status'] = DebtCase::STATUS_OPEN;
                $updates['closed_at'] = null;
                $updates['closure_reason'] = null;
                $caseReopened = true;
            }

            $debtCase->forceFill($updates)->save();
        });

        $match = $match->fresh(['transaction', 'debtCase', 'formOrder']) ?? $match;

        try {
            $this->matcher->matchAndPersist($transaction->fresh() ?? $transaction);
        } catch (\Throwable $e) {
            report($e);
        }

        $importId = $transaction->bank_statement_import_id;
        if ($importId) {
            $import = BankStatementImport::query()->find($importId);
            if ($import) {
                $import->update([
                    'rows_matched' => $import->transactions()
                        ->where('is_incoming', true)
                        ->whereHas('matches', fn ($q) => $q->where('status', BankTransactionMatch::STATUS_SUGGESTED))
                        ->count(),
                ]);
            }
        }

        $message = sprintf(
            'Cofnięto przypisanie przelewu #%d%s.',
            $transaction->id,
            $debtCaseId ? ' ze sprawy #'.$debtCaseId : ''
        );
        if ($caseReopened) {
            $message .= ' Sprawę otwarto ponownie.';
        }

        $warning = null;
        if ($ifirmaResult['attempted'] ?? false) {
            if ($ifirmaResult['success'] ?? false) {
                $message .= ' '.$ifirmaResult['message'];
            } else {
                $warning = $ifirmaResult['message'];
            }
        } elseif (! ($ifirmaResult['success'] ?? false) && ($ifirmaResult['message'] ?? '') !== '') {
            $warning = $ifirmaResult['message'];
        }

        return [
            'success' => true,
            'message' => $message,
            'warning' => $warning,
            'match' => $match,
            'debt_case_id' => $debtCaseId,
            'case_reopened' => $caseReopened,
            'ifirma_removed' => (bool) (($ifirmaResult['attempted'] ?? false) && ($ifirmaResult['success'] ?? false)),
            'ifirma_attempted' => (bool) ($ifirmaResult['attempted'] ?? false),
        ];
    }
}
