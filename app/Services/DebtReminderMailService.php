<?php

namespace App\Services;

use App\Mail\DebtReminderMail;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class DebtReminderMailService
{
    public function __construct(
        private readonly DebtReminderTemplateService $templates,
        private readonly IfirmaApiService $ifirma,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function send(
        DebtCase $case,
        string $toEmail,
        string $subject,
        string $body,
        bool $isTest,
        bool $attachIfirmaPdf,
        ?UploadedFile $uploadedFile = null,
    ): array {
        $attachments = [];
        $tempPaths = [];
        $attachmentLabels = [];

        try {
            if ($attachIfirmaPdf) {
                $lookup = $this->templates->ifirmaPdfLookupKey($case);
                if ($lookup === null) {
                    return [
                        'ok' => false,
                        'message' => 'Brak ID / numeru faktury do pobrania PDF z iFirma.',
                    ];
                }

                $pdf = $this->ifirma->downloadInvoicePdf($lookup);
                if (($pdf['status'] ?? '') !== 'success' || empty($pdf['content'])) {
                    return [
                        'ok' => false,
                        'message' => 'Nie udało się pobrać PDF faktury z iFirma: '.($pdf['message'] ?? 'nieznany błąd'),
                    ];
                }

                $tempPath = sys_get_temp_dir().'/debt-fv-'.Str::uuid()->toString().'.pdf';
                if (file_put_contents($tempPath, $pdf['content']) === false) {
                    return [
                        'ok' => false,
                        'message' => 'Nie udało się zapisać tymczasowego PDF faktury.',
                    ];
                }
                $tempPaths[] = $tempPath;
                $invoiceLabel = $case->invoice_number ?: $case->formOrder?->invoice_number ?: $lookup;
                $safeName = 'FV-'.preg_replace('/[^\w.\-]+/u', '_', (string) $invoiceLabel).'.pdf';
                $attachments[] = [
                    'path' => $tempPath,
                    'name' => $safeName,
                    'mime' => 'application/pdf',
                ];
                $attachmentLabels[] = 'PDF iFirma ('.$safeName.')';
            }

            if ($uploadedFile !== null) {
                $attachments[] = [
                    'path' => $uploadedFile->getRealPath(),
                    'name' => $uploadedFile->getClientOriginalName() ?: 'zalacznik.pdf',
                    'mime' => $uploadedFile->getMimeType() ?: 'application/pdf',
                ];
                $attachmentLabels[] = 'upload ('.$uploadedFile->getClientOriginalName().')';
            }

            Mail::to($toEmail)->send(new DebtReminderMail($body, $subject, $attachments));
        } catch (Throwable $e) {
            Log::error('Debt reminder email failed', [
                'debt_case_id' => $case->id,
                'to' => $toEmail,
                'message' => $e->getMessage(),
            ]);

            foreach ($tempPaths as $path) {
                @unlink($path);
            }

            return [
                'ok' => false,
                'message' => 'Nie udało się wysłać wiadomości. Sprawdź konfigurację poczty lub spróbuj ponownie.',
            ];
        }

        foreach ($tempPaths as $path) {
            @unlink($path);
        }

        $this->logAction($case, $toEmail, $subject, $isTest, $attachmentLabels);

        return [
            'ok' => true,
            'message' => $isTest
                ? 'Wiadomość testowa została wysłana na adres '.$toEmail.'.'
                : 'Wiadomość została wysłana na adres '.$toEmail.'.',
        ];
    }

    /**
     * @param  list<string>  $attachmentLabels
     */
    private function logAction(
        DebtCase $case,
        string $toEmail,
        string $subject,
        bool $isTest,
        array $attachmentLabels,
    ): void {
        $parts = [
            $isTest ? '[TEST]' : '[WYSYŁKA]',
            'Do: '.$toEmail,
            'Temat: '.$subject,
        ];
        if ($attachmentLabels !== []) {
            $parts[] = 'Załączniki: '.implode(', ', $attachmentLabels);
        }

        $case->actions()->create([
            'user_id' => Auth::id(),
            'action_type' => DebtCaseAction::TYPE_EMAIL,
            'channel' => 'email',
            'outcome' => $isTest ? 'test_sent' : 'sent',
            'happened_at' => now(),
            'note' => implode(' · ', $parts),
        ]);

        if (! $isTest) {
            $case->update([
                'assigned_to_id' => Auth::id(),
                'last_action_at' => now(),
                'status' => $case->status === DebtCase::STATUS_OPEN
                    ? DebtCase::STATUS_IN_PROGRESS
                    : $case->status,
            ]);
        }
    }
}
