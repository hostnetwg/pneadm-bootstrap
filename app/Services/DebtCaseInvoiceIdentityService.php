<?php

namespace App\Services;

use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Porównuje kopię faktury na sprawie windykacyjnej z aktualną fakturą zamówienia.
 *
 * Zamówienie jest źródłem prawdy dla bieżącego dokumentu. Puste pola zamówienia
 * nie kasują sprawy — to stan w trakcie korekty, zanim powstanie nowa faktura.
 */
class DebtCaseInvoiceIdentityService
{
    /**
     * @return array{
     *     show_modal: bool,
     *     can_update: bool,
     *     order_invoice_cleared: bool,
     *     invoice_replaced: bool,
     *     clears_ksef: bool,
     *     notes: string|null,
     *     fields: list<array{
     *         key: string,
     *         label: string,
     *         case: string|null,
     *         order: string|null,
     *         changed: bool,
     *         will_write: bool,
     *         apply: string|null,
     *         will_clear: bool
     *     }>
     * }
     */
    public function compare(DebtCase $case): array
    {
        $case->loadMissing('formOrder');
        $order = $case->formOrder;

        $caseInvoice = $this->invoice($case->invoice_number);
        $orderInvoice = $this->invoice($order?->invoice_number);
        $caseKsef = $this->token($case->ksef_number);
        $orderKsef = $this->token($order?->ksef_number);
        $caseId = $this->token($case->ifirma_invoice_id);
        $orderId = $this->token($order?->ifirma_invoice_id);

        $invoiceReplaced = $caseInvoice !== null
            && $orderInvoice !== null
            && $caseInvoice !== $orderInvoice;
        $orderCleared = $order === null
            ? false
            : ($orderInvoice === null && $caseInvoice !== null);

        $sameInvoice = $orderInvoice !== null && $caseInvoice === $orderInvoice;
        $ksefConflict = $sameInvoice
            && $orderKsef !== null
            && $caseKsef !== null
            && $orderKsef !== $caseKsef;
        $idConflict = $sameInvoice
            && $orderId !== null
            && $caseId !== null
            && $orderId !== $caseId;

        $documentChanged = $invoiceReplaced || $idConflict;
        $writeKsef = $documentChanged || $ksefConflict;
        $writeId = $invoiceReplaced || $idConflict;

        $fields = [
            $this->field(
                'invoice_number',
                'Faktura',
                $caseInvoice,
                $orderInvoice,
                $invoiceReplaced,
                $invoiceReplaced ? $orderInvoice : null,
            ),
            $this->field(
                'ksef_number',
                'KSeF',
                $caseKsef,
                $orderKsef,
                $writeKsef && ($caseKsef !== $orderKsef),
                $writeKsef ? $orderKsef : null,
            ),
            $this->field(
                'ifirma_invoice_id',
                'ID iFirma',
                $caseId,
                $orderId,
                $writeId && ($caseId !== $orderId),
                $writeId ? $orderId : null,
            ),
        ];

        $canUpdate = $invoiceReplaced || $ksefConflict || $idConflict;

        $notes = trim((string) ($order?->notes ?? ''));

        return [
            'show_modal' => $canUpdate || $orderCleared,
            'can_update' => $canUpdate,
            'order_invoice_cleared' => $orderCleared,
            'invoice_replaced' => $invoiceReplaced,
            'clears_ksef' => $writeKsef && $orderKsef === null && $caseKsef !== null,
            'notes' => $notes !== '' ? $notes : null,
            'fields' => $fields,
        ];
    }

    /**
     * Wartości do pokazania na karcie sprawy.
     * Przy tym samym numerze FV brakujące KSeF i ID uzupełnia zamówienie.
     * Przy innym numerze karta zostaje przy kopii ze sprawy.
     *
     * @return array{invoice_number: string|null, ksef_number: string|null, ifirma_invoice_id: string|null}
     */
    public function presentation(DebtCase $case): array
    {
        $case->loadMissing('formOrder');
        $order = $case->formOrder;

        $caseInvoice = $this->invoice($case->invoice_number);
        $orderInvoice = $this->invoice($order?->invoice_number);
        $sameInvoice = $caseInvoice !== null && $caseInvoice === $orderInvoice;
        $caseMissingInvoice = $caseInvoice === null;

        $caseKsef = $this->token($case->ksef_number);
        $orderKsef = $this->token($order?->ksef_number);
        $caseId = $this->token($case->ifirma_invoice_id);
        $orderId = $this->token($order?->ifirma_invoice_id);

        $mayUseOrder = $sameInvoice || $caseMissingInvoice;

        return [
            'invoice_number' => $caseInvoice ?? $orderInvoice,
            'ksef_number' => $mayUseOrder ? ($caseKsef ?? $orderKsef) : $caseKsef,
            'ifirma_invoice_id' => $mayUseOrder ? ($caseId ?? $orderId) : $caseId,
        ];
    }

    /**
     * @return array{success: bool, changed: bool, message: string}
     */
    public function alignCaseToOrder(DebtCase $case, ?User $user = null): array
    {
        return DB::transaction(function () use ($case, $user) {
            $locked = DebtCase::query()->lockForUpdate()->find($case->id);
            if ($locked === null) {
                return [
                    'success' => false,
                    'changed' => false,
                    'message' => 'Nie znaleziono sprawy.',
                ];
            }

            $order = $locked->form_order_id
                ? FormOrder::query()->lockForUpdate()->find($locked->form_order_id)
                : null;
            $locked->setRelation('formOrder', $order);

            $before = $this->compare($locked);
            if (! $before['can_update'] || $order === null) {
                return [
                    'success' => false,
                    'changed' => false,
                    'message' => $before['order_invoice_cleared']
                        ? 'Zamówienie nie ma aktualnego numeru faktury. Dane sprawy zostały bez zmian.'
                        : 'Dane faktury na sprawie zgadzają się z zamówieniem.',
                ];
            }

            foreach ($before['fields'] as $field) {
                if (! $field['will_write']) {
                    continue;
                }
                $locked->{$field['key']} = $field['apply'];
            }

            if ($before['invoice_replaced']) {
                if ($order->product_price !== null) {
                    $locked->amount_gross = $order->product_price;
                }
                if ($order->invoice_issue_date !== null) {
                    $locked->invoice_date = $order->invoice_issue_date->toDateString();
                }
                if ($order->invoice_due_date !== null) {
                    $locked->due_date = $order->invoice_due_date->toDateString();
                }
            }

            $locked->last_action_at = now();
            if ($user !== null) {
                $locked->assigned_to_id = $user->id;
            }
            $locked->save();

            $locked->actions()->create([
                'user_id' => $user?->id,
                'action_type' => DebtCaseAction::TYPE_INVOICE_IDENTITY,
                'channel' => 'panel',
                'happened_at' => now(),
                'note' => $this->historyNote($before, $order),
            ]);

            $case->setRawAttributes($locked->getAttributes(), true);
            $case->syncOriginal();
            $case->setRelation('formOrder', $order);

            return [
                'success' => true,
                'changed' => true,
                'message' => 'Zaktualizowano dane faktury na podstawie zamówienia.',
            ];
        });
    }

    /**
     * Dopisz brakujący numer, KSeF albo ID, gdy to ten sam dokument co w zamówieniu.
     * Nie rusza sprawy, która ma już inny numer faktury.
     */
    public function fillBlanksWhenSameInvoice(DebtCase $case): bool
    {
        $case->loadMissing('formOrder');
        $order = $case->formOrder;
        if ($order === null) {
            return false;
        }

        $caseInvoice = $this->invoice($case->invoice_number);
        $orderInvoice = $this->invoice($order->invoice_number);
        if ($orderInvoice === null || ($caseInvoice !== null && $caseInvoice !== $orderInvoice)) {
            return false;
        }

        $dirty = false;
        if ($caseInvoice === null) {
            $case->invoice_number = $orderInvoice;
            $dirty = true;
        }
        if ($this->token($case->ksef_number) === null && ($ksef = $this->token($order->ksef_number)) !== null) {
            $case->ksef_number = $ksef;
            $dirty = true;
        }
        if ($this->token($case->ifirma_invoice_id) === null && ($id = $this->token($order->ifirma_invoice_id)) !== null) {
            $case->ifirma_invoice_id = $id;
            $dirty = true;
        }

        return $dirty;
    }

    /**
     * @param  array{
     *     fields: list<array{label: string, case: string|null, will_write: bool, apply: string|null}>
     * }  $compare
     */
    private function historyNote(array $compare, FormOrder $order): string
    {
        $parts = ['Zaktualizowano dane faktury na podstawie zamówienia #'.$order->id.'.'];
        foreach ($compare['fields'] as $field) {
            if (! $field['will_write']) {
                continue;
            }
            $from = $field['case'] ?? 'brak';
            $to = $field['apply'] ?? 'brak';
            $parts[] = $field['label'].': '.$from.' → '.$to.'.';
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     case: string|null,
     *     order: string|null,
     *     changed: bool,
     *     will_write: bool,
     *     apply: string|null,
     *     will_clear: bool
     * }
     */
    private function field(
        string $key,
        string $label,
        ?string $caseValue,
        ?string $orderValue,
        bool $willWrite,
        ?string $apply,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'case' => $caseValue,
            'order' => $orderValue,
            'changed' => $caseValue !== $orderValue,
            'will_write' => $willWrite,
            'apply' => $willWrite ? $apply : null,
            'will_clear' => $willWrite && $apply === null && $caseValue !== null,
        ];
    }

    private function invoice(mixed $value): ?string
    {
        $value = trim(str_replace("\u{00A0}", '', (string) $value));

        if ($value === '' || $value === '0') {
            return null;
        }

        return $value;
    }

    private function token(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
