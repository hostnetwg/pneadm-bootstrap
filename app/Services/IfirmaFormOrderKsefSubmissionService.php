<?php

namespace App\Services;

use App\Models\FormOrder;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IfirmaFormOrderKsefSubmissionService
{
    public function submit(
        FormOrder $zamowienie,
        IfirmaApiService $ifirmaService,
        string $invoiceId,
        ?string $invoiceNumber,
        Request $request
    ): JsonResponse {
        $invoiceNumber = trim((string) ($invoiceNumber ?: $zamowienie->invoice_number ?: $invoiceId));

        Log::info('iFirma Invoice With KSeF: Przesyłanie do KSeF', [
            'order_id' => $zamowienie->id,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
        ]);

        $ksefResult = $ifirmaService->sendInvoiceToKsef($invoiceId, 'fakturakraj');

        if ($ksefResult['status'] !== 'success') {
            $ksefError = $ksefResult['message'] ?? 'Nieznany błąd przesyłania do KSeF';

            $zamowienie->ksef_status = 'failed';
            $zamowienie->ksef_error = $ksefError;
            $zamowienie->save();

            Log::error('iFirma Invoice With KSeF: Błąd przesyłania do KSeF', [
                'order_id' => $zamowienie->id,
                'invoice_id' => $invoiceId,
                'error' => $ksefError,
                'ksef_response' => $ksefResult,
            ]);

            return response()->json($this->partialFailurePayload(
                'Faktura została wystawiona w iFirma, ale nie udało się przesłać do KSeF',
                'ksef_send',
                $invoiceId,
                $invoiceNumber,
                ['ksef_error' => $ksefError, 'can_retry' => true]
            ), 500);
        }

        $ksefNumber = $ifirmaService->extractNumerKSeFFromInvoicePayload(
            isset($ksefResult['data']) && is_array($ksefResult['data']) ? $ksefResult['data'] : null
        );

        $poll = ($ksefNumber === null || $ksefNumber === '')
            ? $ifirmaService->waitForKsefInvoiceAccepted($invoiceId)
            : ['outcome' => 'accepted', 'numer_ksef' => $ksefNumber, 'rejection_message' => null, 'attempts' => 0];

        if ($poll['outcome'] === 'timeout') {
            $pendingMsg = 'Faktura jest w iFirma i została przekazana do KSeF, ale Ministerstwo Finansów '
                .'nie nadało jeszcze numeru KSeF w czasie oczekiwania. Sprawdź status w iFirma — '
                .'numer faktury w zamówieniu jest już zapisany. E-mail z fakturą nie został wysłany.';

            $zamowienie->ksef_status = 'pending';
            $zamowienie->ksef_error = $pendingMsg;
            $zamowienie->ksef_sent_at = null;
            $zamowienie->save();

            Log::warning('iFirma Invoice With KSeF: timeout oczekiwania na NumerKSeF', [
                'order_id' => $zamowienie->id,
                'invoice_id' => $invoiceId,
                'poll_attempts' => $poll['attempts'],
            ]);

            return response()->json($this->partialFailurePayload(
                $pendingMsg,
                'ksef_acceptance_timeout',
                $invoiceId,
                $invoiceNumber,
                ['poll_attempts' => $poll['attempts'], 'can_retry' => true, 'ksef_status' => 'pending']
            ), 504);
        }

        if ($poll['outcome'] === 'rejected') {
            $ksefError = $poll['rejection_message'] ?? 'KSeF odrzucił lub nie przyjął faktury';

            $zamowienie->ksef_status = 'failed';
            $zamowienie->ksef_error = $ksefError;
            $zamowienie->save();

            Log::error('iFirma Invoice With KSeF: odrzucenie / błąd po przekazaniu do KSeF', [
                'order_id' => $zamowienie->id,
                'invoice_id' => $invoiceId,
                'error' => $ksefError,
                'poll_attempts' => $poll['attempts'],
            ]);

            return response()->json($this->partialFailurePayload(
                'Faktura została wystawiona w iFirma, ale nie została zaakceptowana w KSeF',
                'ksef_rejected',
                $invoiceId,
                $invoiceNumber,
                ['ksef_error' => $ksefError, 'can_retry' => true, 'poll_attempts' => $poll['attempts']]
            ), 500);
        }

        $ksefNumber = $poll['numer_ksef'];

        $zamowienie->update([
            'ksef_status' => 'sent',
            'ksef_sent_at' => now(),
            'ksef_error' => null,
            'ksef_number' => $ksefNumber,
        ]);
        $zamowienie->refresh();

        $sendEmail = $request->input('send_email', false);
        $emailsSent = [];
        $emailErrors = [];

        if ($sendEmail && $invoiceId !== '') {
            $emails = [];

            if (! empty($zamowienie->orderer_email)) {
                $emails[] = strtolower(trim($zamowienie->orderer_email));
            }

            if (! empty(trim($zamowienie->display_participant_email ?? ''))) {
                $participantEmail = strtolower(trim($zamowienie->display_participant_email));
                if (! in_array($participantEmail, $emails, true)) {
                    $emails[] = $participantEmail;
                }
            }

            foreach ($emails as $email) {
                try {
                    $sendResult = $ifirmaService->sendInvoiceByEmail(
                        $invoiceId,
                        $email,
                        $invoiceNumber,
                        $zamowienie->id,
                        'invoice'
                    );

                    if ($sendResult['status'] === 'success') {
                        $emailsSent[] = $email;
                    } else {
                        $emailErrors[] = [
                            'email' => $email,
                            'error' => $sendResult['message'] ?? 'Nieznany błąd',
                        ];
                    }
                } catch (Exception $e) {
                    $emailErrors[] = [
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Exception podczas wysyłki faktury z KSeF', [
                        'invoice_id' => $invoiceId,
                        'email' => $email,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }

        $message = 'Faktura została wystawiona w iFirma.pl';
        if ($ksefNumber) {
            $message .= " i przesłana do KSeF (nr: {$ksefNumber})";
        }
        if ($emailsSent !== []) {
            $message .= ' i wysłana na: '.implode(', ', $emailsSent);
        }
        if ($emailErrors !== []) {
            $message .= ' (Błędy wysyłki e-mail: '.count($emailErrors).')';
        }

        return response()->json([
            'success' => true,
            'phase' => 'ksef',
            'step' => 'completed',
            'message' => $message,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'invoice_created' => true,
            'ksef_number' => $ksefNumber,
            'ksef_sent_at' => $zamowienie->ksef_sent_at?->toDateTimeString(),
            'email_sent' => $emailsSent !== [],
            'emails_sent' => $emailsSent,
            'email_errors' => $emailErrors,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function partialFailurePayload(
        string $error,
        string $step,
        string $invoiceId,
        string $invoiceNumber,
        array $extra = []
    ): array {
        return array_merge([
            'success' => false,
            'partial_success' => true,
            'invoice_created' => true,
            'error' => $error,
            'step' => $step,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
        ], $extra);
    }
}
