<?php

namespace App\Services;

use App\Models\FormOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IfirmaFormOrderKsefSubmissionService
{
    public function __construct(
        private IfirmaFormOrderKsefBackgroundService $background
    ) {}

    public function submit(
        FormOrder $zamowienie,
        IfirmaApiService $ifirmaService,
        string $invoiceId,
        ?string $invoiceNumber,
        Request $request
    ): JsonResponse {
        $invoiceNumber = $this->resolveHumanInvoiceNumber($invoiceNumber, $zamowienie, $invoiceId);
        $sendEmail = filter_var($request->input('send_email', false), FILTER_VALIDATE_BOOLEAN);

        if (empty($zamowienie->ifirma_invoice_id) || (string) $zamowienie->ifirma_invoice_id !== (string) $invoiceId) {
            $zamowienie->ifirma_invoice_id = (string) $invoiceId;
        }

        if ($sendEmail && ! $zamowienie->ksef_email_pending) {
            $zamowienie->ksef_email_pending = true;
        }

        if ($zamowienie->isDirty()) {
            $zamowienie->save();
        }

        $this->background->queueAfterInvoice(
            $zamowienie,
            $sendEmail,
            $request->user()?->id
        );

        $zamowienie->refresh();

        return response()->json([
            'success' => true,
            'phase' => 'ksef',
            'step' => 'ksef_queued',
            'ksef_queued' => true,
            'message' => $invoiceNumber !== ''
                ? 'Faktura '.$invoiceNumber.' jest w iFirma. Wysyłka do KSeF i numer MF idą w tle.'
                : 'Faktura jest w iFirma. Wysyłka do KSeF i numer MF idą w tle.',
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'invoice_created' => true,
            'invoice_issue_date' => $zamowienie->invoice_issue_date?->toDateString(),
            'invoice_due_date' => $zamowienie->invoice_due_date?->toDateString(),
            'ksef_number' => $zamowienie->ksef_number,
            'ksef_status' => $zamowienie->ksef_status,
            'ksef_error' => $zamowienie->ksef_error,
            'ksef_email_pending' => (bool) $zamowienie->ksef_email_pending,
        ]);
    }

    /**
     * Klasyczny PelnyNumer — nigdy Identyfikator iFirma.
     */
    private function resolveHumanInvoiceNumber(
        ?string $invoiceNumber,
        FormOrder $zamowienie,
        string $invoiceId
    ): string {
        foreach ([$invoiceNumber, $zamowienie->invoice_number] as $candidate) {
            $trimmed = trim((string) $candidate);
            if ($trimmed === '' || $trimmed === $invoiceId || preg_match('/^\d+$/', $trimmed) === 1) {
                continue;
            }

            return $trimmed;
        }

        return '';
    }
}
