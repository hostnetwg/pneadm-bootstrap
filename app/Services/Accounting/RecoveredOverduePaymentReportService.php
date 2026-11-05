<?php

namespace App\Services\Accounting;

use App\Models\BankTransactionMatch;
use App\Models\DebtCaseAction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecoveredOverduePaymentReportService
{
    public const SESSION_KEY = 'recovered_overdue_payment_notice';

    public function baseQuery(array $filters = []): Builder
    {
        $query = BankTransactionMatch::query()
            ->select('bank_transaction_matches.*')
            ->join('bank_transactions', 'bank_transactions.id', '=', 'bank_transaction_matches.bank_transaction_id')
            ->leftJoin('debt_cases', 'debt_cases.id', '=', 'bank_transaction_matches.debt_case_id')
            ->leftJoin('form_orders as case_orders', 'case_orders.id', '=', 'debt_cases.form_order_id')
            ->leftJoin('form_orders as match_orders', 'match_orders.id', '=', 'bank_transaction_matches.form_order_id')
            ->with([
                'transaction.import',
                'debtCase.formOrder.primaryParticipant',
                'formOrder.primaryParticipant',
                'acceptedBy',
            ])
            ->where('bank_transaction_matches.status', BankTransactionMatch::STATUS_ACCEPTED)
            ->whereRaw('COALESCE(debt_cases.due_date, case_orders.invoice_due_date, match_orders.invoice_due_date) IS NOT NULL')
            ->whereRaw('bank_transactions.operation_date > COALESCE(debt_cases.due_date, case_orders.invoice_due_date, match_orders.invoice_due_date)');

        if (! empty($filters['from'])) {
            $query->whereDate('bank_transactions.operation_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('bank_transactions.operation_date', '<=', $filters['to']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like, $search) {
                $q->where('debt_cases.invoice_number', 'like', $like)
                    ->orWhere('debt_cases.ksef_number', 'like', $like)
                    ->orWhere('case_orders.invoice_number', 'like', $like)
                    ->orWhere('case_orders.ksef_number', 'like', $like)
                    ->orWhere('case_orders.buyer_name', 'like', $like)
                    ->orWhere('case_orders.recipient_name', 'like', $like)
                    ->orWhere('case_orders.orderer_name', 'like', $like)
                    ->orWhere('case_orders.orderer_email', 'like', $like)
                    ->orWhere('match_orders.invoice_number', 'like', $like)
                    ->orWhere('match_orders.buyer_name', 'like', $like)
                    ->orWhere('bank_transactions.description', 'like', $like)
                    ->orWhere('bank_transactions.account_label', 'like', $like);

                if (ctype_digit($search)) {
                    $id = (int) $search;
                    $q->orWhere('debt_cases.id', $id)
                        ->orWhere('case_orders.id', $id)
                        ->orWhere('match_orders.id', $id)
                        ->orWhere('bank_transactions.id', $id);
                }
            });
        }

        $contact = (string) ($filters['contact'] ?? 'all');
        if ($contact === 'with') {
            $query->whereExists($this->contactExistsSql());
        } elseif ($contact === 'without') {
            $query->whereNotExists($this->contactExistsSql());
        }

        return $query
            ->orderByDesc('bank_transactions.operation_date')
            ->orderByDesc('bank_transaction_matches.accepted_at')
            ->orderByDesc('bank_transaction_matches.id');
    }

    public function rowForMatch(BankTransactionMatch $match): ?array
    {
        $match->loadMissing([
            'transaction.import',
            'debtCase.formOrder.primaryParticipant',
            'formOrder.primaryParticipant',
            'acceptedBy',
        ]);

        $transaction = $match->transaction;
        $case = $match->debtCase;
        $order = $case?->formOrder ?: $match->formOrder;
        $dueDate = $case?->due_date ?: $order?->invoice_due_date;
        $operationDate = $transaction?->operation_date;

        if (! $transaction || ! $operationDate || ! $dueDate || ! $operationDate->gt($dueDate)) {
            return null;
        }

        $acceptedAt = $match->accepted_at ?: now();
        $counts = $case
            ? $this->notificationCounts($case->id, $acceptedAt)
            : ['email' => 0, 'sms' => 0, 'phone' => 0, 'total' => 0];

        return [
            'match' => $match,
            'transaction' => $transaction,
            'case' => $case,
            'order' => $order,
            'due_date' => $dueDate,
            'operation_date' => $operationDate,
            'delay_days' => $dueDate->copy()->startOfDay()->diffInDays($operationDate->copy()->startOfDay()),
            'allocated_amount' => $match->allocated_amount !== null ? (float) $match->allocated_amount : (float) $transaction->amount,
            'invoice_number' => $case?->invoice_number ?: $order?->invoice_number,
            'customer_name' => $this->customerName($order),
            'notification_counts' => $counts,
            'accepted_at' => $match->accepted_at,
            'accepted_by' => $match->acceptedBy,
        ];
    }

    public function rowsForMatches(iterable $matches): Collection
    {
        return collect($matches)
            ->map(fn (BankTransactionMatch $match): ?array => $this->rowForMatch($match))
            ->filter()
            ->values();
    }

    public function sessionPayloadForMatches(iterable $matches): ?array
    {
        $rows = $this->rowsForMatches($matches);
        if ($rows->isEmpty()) {
            return null;
        }

        if ($rows->count() === 1) {
            $row = $rows->first();

            return [
                'message' => sprintf(
                    'Zarejestrowano zalegającą wpłatę: %d dni po terminie, powiadomienia: %d (e-mail %d, SMS %d, telefon %d).',
                    $row['delay_days'],
                    $row['notification_counts']['total'],
                    $row['notification_counts']['email'],
                    $row['notification_counts']['sms'],
                    $row['notification_counts']['phone'],
                ),
                'rows_count' => 1,
            ];
        }

        $maxDelay = (int) $rows->max('delay_days');
        $totalNotifications = (int) $rows->sum(fn (array $row): int => $row['notification_counts']['total']);

        return [
            'message' => sprintf(
                'Zarejestrowano %d zalegające wpłaty. Największe opóźnienie: %d dni. Powiadomienia łącznie: %d.',
                $rows->count(),
                $maxDelay,
                $totalNotifications,
            ),
            'rows_count' => $rows->count(),
        ];
    }

    public function csvRows(Collection $rows): array
    {
        return $rows->map(function (array $row): array {
            return [
                'data_wplaty' => $row['operation_date']->format('Y-m-d'),
                'dni_po_terminie' => $row['delay_days'],
                'kwota' => number_format($row['allocated_amount'], 2, '.', ''),
                'sprawa_id' => $row['case']?->id,
                'zamowienie_id' => $row['order']?->id,
                'faktura' => $row['invoice_number'],
                'klient' => $row['customer_name'],
                'email' => $row['notification_counts']['email'],
                'sms' => $row['notification_counts']['sms'],
                'telefon' => $row['notification_counts']['phone'],
                'powiadomienia_razem' => $row['notification_counts']['total'],
                'przelew_id' => $row['transaction']->id,
                'import_id' => $row['transaction']->bank_statement_import_id,
            ];
        })->all();
    }

    private function notificationCounts(int $debtCaseId, Carbon $acceptedAt): array
    {
        $base = DebtCaseAction::query()
            ->where('debt_case_id', $debtCaseId)
            ->where('happened_at', '<=', $acceptedAt);

        $email = (clone $base)
            ->where('action_type', DebtCaseAction::TYPE_EMAIL)
            ->where('outcome', 'sent')
            ->count();

        $sms = (clone $base)
            ->where('action_type', DebtCaseAction::TYPE_SMS)
            ->where('outcome', 'sent')
            ->count();

        $phone = (clone $base)
            ->where('action_type', DebtCaseAction::TYPE_PHONE)
            ->count();

        return [
            'email' => (int) $email,
            'sms' => (int) $sms,
            'phone' => (int) $phone,
            'total' => (int) ($email + $sms + $phone),
        ];
    }

    private function customerName(?object $order): string
    {
        if (! $order) {
            return '—';
        }

        return Str::of($order->recipient_name ?: $order->buyer_name ?: $order->orderer_name ?: '—')
            ->squish()
            ->toString();
    }

    private function contactExistsSql(): \Closure
    {
        return function ($query): void {
            $query->select(DB::raw(1))
                ->from('debt_case_actions')
                ->whereColumn('debt_case_actions.debt_case_id', 'bank_transaction_matches.debt_case_id')
                ->whereColumn('debt_case_actions.happened_at', '<=', 'bank_transaction_matches.accepted_at')
                ->where(function ($action) {
                    $action->where(function ($email) {
                        $email->where('action_type', DebtCaseAction::TYPE_EMAIL)
                            ->where('outcome', 'sent');
                    })->orWhere(function ($sms) {
                        $sms->where('action_type', DebtCaseAction::TYPE_SMS)
                            ->where('outcome', 'sent');
                    })->orWhere('action_type', DebtCaseAction::TYPE_PHONE);
                });
        };
    }
}
