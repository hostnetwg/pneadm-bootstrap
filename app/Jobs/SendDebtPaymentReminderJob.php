<?php

namespace App\Jobs;

use App\Models\DebtPaymentReminderCampaign;
use App\Models\DebtPaymentReminderCampaignItem;
use App\Models\User;
use App\Services\Debt\DebtPaymentReminderCampaignService;
use App\Services\Debt\DebtPaymentReminderEligibilityService;
use App\Services\DebtCaseAutoCloseService;
use App\Services\DebtReminderMailService;
use App\Services\DebtReminderSmsService;
use App\Services\DebtReminderTemplateService;
use App\Services\IfirmaInvoicePaymentStatusService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendDebtPaymentReminderJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $campaignItemId,
    ) {}

    public function uniqueId(): string
    {
        return 'debt-payment-reminder-item-'.$this->campaignItemId;
    }

    public function handle(
        DebtPaymentReminderEligibilityService $eligibility,
        DebtPaymentReminderCampaignService $campaigns,
        DebtReminderTemplateService $templates,
        DebtReminderMailService $mail,
        DebtReminderSmsService $sms,
        IfirmaInvoicePaymentStatusService $ifirma,
        DebtCaseAutoCloseService $autoClose,
    ): void {
        if ($this->batch()?->cancelled()) {
            $this->markCancelled();

            return;
        }

        $item = DebtPaymentReminderCampaignItem::query()
            ->with(['campaign', 'debtCase.formOrder'])
            ->find($this->campaignItemId);

        if ($item === null) {
            return;
        }

        $campaign = $item->campaign;
        if ($campaign === null
            || $campaign->status === DebtPaymentReminderCampaign::STATUS_CANCELLED
            || in_array($item->status, [
                DebtPaymentReminderCampaignItem::STATUS_SENT,
                DebtPaymentReminderCampaignItem::STATUS_SKIPPED_IFIRMA,
                DebtPaymentReminderCampaignItem::STATUS_SKIPPED_CASE,
                DebtPaymentReminderCampaignItem::STATUS_REQUIRES_REVIEW,
                DebtPaymentReminderCampaignItem::STATUS_CANCELLED,
            ], true)
        ) {
            return;
        }

        $claimed = DB::transaction(function () use ($item) {
            $locked = DebtPaymentReminderCampaignItem::query()
                ->whereKey($item->id)
                ->lockForUpdate()
                ->first();
            if ($locked === null
                || $locked->status !== DebtPaymentReminderCampaignItem::STATUS_PENDING
            ) {
                return false;
            }
            $locked->update([
                'status' => DebtPaymentReminderCampaignItem::STATUS_PROCESSING,
            ]);

            return true;
        });

        if (! $claimed) {
            return;
        }

        $item->refresh();
        $case = $item->debtCase?->fresh(['formOrder', 'bankTransactionMatches.transaction']);
        if ($case === null) {
            $this->finishItem($item, DebtPaymentReminderCampaignItem::STATUS_SKIPPED_CASE, 'missing_case');

            return;
        }

        $actor = $campaign->started_by
            ? User::query()->find($campaign->started_by)
            : null;

        $reason = $eligibility->exclusionReason(
            $case,
            $campaign->audience,
            (bool) $campaign->send_email,
            (bool) $campaign->send_sms,
        );
        if ($reason !== null) {
            $this->finishItem(
                $item,
                DebtPaymentReminderCampaignItem::STATUS_SKIPPED_CASE,
                $reason,
            );

            return;
        }

        if ($campaign->check_ifirma) {
            $sync = $ifirma->syncDebtCase($case, $actor);
            $case->refresh();

            if (! ($sync['success'] ?? false)) {
                $this->finishItem(
                    $item,
                    DebtPaymentReminderCampaignItem::STATUS_REQUIRES_REVIEW,
                    'ifirma_error',
                    mb_substr((string) ($sync['message'] ?? 'Błąd iFirma'), 0, 500),
                );

                return;
            }

            $status = $sync['status'] ?? $case->ifirma_payment_status;
            if ($status === IfirmaInvoicePaymentStatusService::STATUS_PAID) {
                $autoClose->closeIfFullyPaid(
                    $case->fresh(),
                    $actor,
                    IfirmaInvoicePaymentStatusService::STATUS_PAID,
                    DebtCaseAutoCloseService::CLOSURE_REASON_IFIRMA_SYNC
                );
                $this->finishItem(
                    $item,
                    DebtPaymentReminderCampaignItem::STATUS_SKIPPED_IFIRMA,
                    DebtPaymentReminderEligibilityService::REASON_IFIRMA_PAID,
                );

                return;
            }
            if ($status === IfirmaInvoicePaymentStatusService::STATUS_PARTIAL) {
                $this->finishItem(
                    $item,
                    DebtPaymentReminderCampaignItem::STATUS_SKIPPED_IFIRMA,
                    DebtPaymentReminderEligibilityService::REASON_IFIRMA_PARTIAL,
                );

                return;
            }

            $reasonAfter = $eligibility->exclusionReason(
                $case->fresh(['formOrder', 'bankTransactionMatches.transaction']),
                $campaign->audience,
                (bool) $campaign->send_email,
                (bool) $campaign->send_sms,
            );
            if ($reasonAfter !== null) {
                $this->finishItem(
                    $item,
                    DebtPaymentReminderCampaignItem::STATUS_SKIPPED_CASE,
                    $reasonAfter,
                );

                return;
            }
        }

        $emailOutcome = null;
        $smsOutcome = null;
        $errors = [];

        $wantEmail = (bool) $campaign->send_email
            || ((bool) $campaign->send_sms && (bool) $campaign->sms_companion_email);
        $wantSms = (bool) $campaign->send_sms;

        if ($wantEmail) {
            $emailTemplate = $campaign->send_email
                ? $campaign->email_template
                : DebtReminderTemplateService::TEMPLATE_REMINDER;
            $built = $templates->build($case, $emailTemplate);
            $attachPdf = (bool) $campaign->attach_ifirma_pdf
                && $campaigns->canAttachSafeIfirmaPdf($case);

            if ((bool) $campaign->attach_ifirma_pdf && ! $attachPdf) {
                $errors[] = 'Brak bezpiecznego ID iFirma do PDF.';
                $emailOutcome = 'failed';
            } else {
                $body = $templates->syncInvoiceAttachmentSentenceInBody(
                    $built['body'],
                    $attachPdf,
                    $built['invoice_attachment_sentence'] ?? null,
                );
                $result = $mail->send(
                    $case,
                    (string) $case->formOrder?->orderer_email,
                    $built['subject'],
                    $body,
                    false,
                    $attachPdf,
                    false,
                    null,
                    $actor,
                );
                if ($result['ok']) {
                    $emailOutcome = 'sent';
                } else {
                    $emailOutcome = 'failed';
                    $errors[] = $result['message'];
                }
            }
        }

        if ($wantSms && ($emailOutcome !== 'failed' || (bool) $campaign->send_email === false || ! (bool) $campaign->sms_companion_email)) {
            // Gdy companion email wymagany i padł — nie wysyłaj SMS (fail closed na pakiecie).
            if ((bool) $campaign->sms_companion_email && (bool) $campaign->send_sms && $emailOutcome === 'failed') {
                $smsOutcome = 'skipped';
            } else {
                $smsBuilt = $templates->buildSms($case, $campaign->sms_template);
                $smsBody = $smsBuilt['body'];
                if ($campaign->strip_sms_diacritics) {
                    $smsBody = $templates->toGsmFriendlySms($smsBody);
                }
                $phone = (string) ($case->formOrder?->orderer_phone ?? '');
                $smsResult = $sms->send($case, $phone, $smsBody, false, $actor);
                if ($smsResult['ok']) {
                    $smsOutcome = 'sent';
                } else {
                    $smsOutcome = 'failed';
                    $errors[] = $smsResult['message'];
                }
            }
        }

        $sentSomething = $emailOutcome === 'sent' || $smsOutcome === 'sent';
        $failedHard = ($wantEmail && $emailOutcome === 'failed' && ! $sentSomething)
            || ($wantSms && $smsOutcome === 'failed' && ! $sentSomething && $emailOutcome !== 'sent');

        if ($sentSomething) {
            $this->finishItem(
                $item,
                DebtPaymentReminderCampaignItem::STATUS_SENT,
                null,
                $errors !== [] ? mb_substr(implode(' · ', $errors), 0, 500) : null,
                $emailOutcome,
                $smsOutcome,
            );

            return;
        }

        if ($failedHard || $errors !== []) {
            $this->finishItem(
                $item,
                DebtPaymentReminderCampaignItem::STATUS_FAILED,
                null,
                mb_substr(implode(' · ', $errors) ?: 'Błąd wysyłki', 0, 500),
                $emailOutcome,
                $smsOutcome,
            );

            return;
        }

        $this->finishItem(
            $item,
            DebtPaymentReminderCampaignItem::STATUS_REQUIRES_REVIEW,
            'no_channel',
            'Brak kanału do wysyłki.',
            $emailOutcome,
            $smsOutcome,
        );
    }

    public function failed(?Throwable $e): void
    {
        $item = DebtPaymentReminderCampaignItem::query()->find($this->campaignItemId);
        if ($item === null) {
            return;
        }
        if (in_array($item->status, [
            DebtPaymentReminderCampaignItem::STATUS_SENT,
            DebtPaymentReminderCampaignItem::STATUS_CANCELLED,
        ], true)) {
            return;
        }
        $this->finishItem(
            $item,
            DebtPaymentReminderCampaignItem::STATUS_FAILED,
            null,
            mb_substr($e?->getMessage() ?? 'Job failed', 0, 500),
        );
    }

    private function markCancelled(): void
    {
        $item = DebtPaymentReminderCampaignItem::query()->find($this->campaignItemId);
        if ($item === null || $item->status !== DebtPaymentReminderCampaignItem::STATUS_PENDING) {
            return;
        }
        $this->finishItem($item, DebtPaymentReminderCampaignItem::STATUS_CANCELLED, 'cancelled');
    }

    private function finishItem(
        DebtPaymentReminderCampaignItem $item,
        string $status,
        ?string $skipReason = null,
        ?string $error = null,
        ?string $emailOutcome = null,
        ?string $smsOutcome = null,
    ): void {
        $item->update([
            'status' => $status,
            'skip_reason' => $skipReason,
            'error_message' => $error,
            'email_outcome' => $emailOutcome,
            'sms_outcome' => $smsOutcome,
            'processed_at' => now(),
        ]);
    }
}
