<?php

namespace App\Services\Debt;

use App\Jobs\SendDebtPaymentReminderJob;
use App\Models\DebtCase;
use App\Models\DebtPaymentReminderCampaign;
use App\Models\DebtPaymentReminderCampaignItem;
use App\Models\User;
use App\Services\DebtReminderTemplateService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class DebtPaymentReminderCampaignService
{
    public function __construct(
        private readonly DebtPaymentReminderGateService $gate,
        private readonly DebtPaymentReminderEligibilityService $eligibility,
        private readonly DebtReminderTemplateService $templates,
    ) {}

    /**
     * @param  list<int>  $selectedIds
     * @param  array{
     *     send_email: bool,
     *     send_sms: bool,
     *     email_template: string,
     *     sms_template: string,
     *     check_ifirma: bool,
     *     attach_ifirma_pdf: bool,
     *     strip_sms_diacritics: bool,
     *     sms_companion_email: bool
     * }  $options
     */
    public function start(string $audience, array $selectedIds, array $options, User $actor): DebtPaymentReminderCampaign
    {
        $this->gate->assertAllowed();

        if (! in_array($audience, [
            DebtPaymentReminderCampaign::AUDIENCE_STANDARD,
            DebtPaymentReminderCampaign::AUDIENCE_VIP,
        ], true)) {
            throw ValidationException::withMessages(['audience' => 'Nieprawidłowa zakładka kampanii.']);
        }

        $sendEmail = (bool) ($options['send_email'] ?? false);
        $sendSms = (bool) ($options['send_sms'] ?? false);
        if (! $sendEmail && ! $sendSms) {
            throw ValidationException::withMessages(['channels' => 'Wybierz co najmniej jeden kanał (e-mail lub SMS).']);
        }

        $emailTemplate = (string) ($options['email_template'] ?? DebtReminderTemplateService::TEMPLATE_REMINDER);
        $smsTemplate = (string) ($options['sms_template'] ?? DebtReminderTemplateService::TEMPLATE_REMINDER);
        $allowedTemplates = [
            DebtReminderTemplateService::TEMPLATE_REMINDER,
            DebtReminderTemplateService::TEMPLATE_VIP,
        ];
        if (! in_array($emailTemplate, $allowedTemplates, true)
            || ! in_array($smsTemplate, $allowedTemplates, true)
        ) {
            throw ValidationException::withMessages(['template' => 'Niedozwolony szablon w kampanii masowej.']);
        }

        $selectedIds = array_values(array_unique(array_map('intval', $selectedIds)));
        if ($selectedIds === []) {
            throw ValidationException::withMessages(['selected_ids' => 'Zaznacz co najmniej jedną sprawę.']);
        }

        $lists = $this->eligibility->buildLists($audience, $sendEmail, $sendSms);
        $eligibleIds = $lists['candidates']->pluck('id')->map(fn ($id) => (int) $id)->all();
        $invalid = array_diff($selectedIds, $eligibleIds);
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'selected_ids' => 'Część zaznaczonych spraw nie kwalifikuje się już do wysyłki. Odśwież listę.',
            ]);
        }

        return DB::transaction(function () use (
            $audience,
            $selectedIds,
            $options,
            $actor,
            $sendEmail,
            $sendSms,
            $emailTemplate,
            $smsTemplate,
        ) {
            // Ponowna bramka w transakcji (race z inną kampanią).
            $this->gate->assertAllowed();

            $campaign = DebtPaymentReminderCampaign::create([
                'audience' => $audience,
                'status' => DebtPaymentReminderCampaign::STATUS_PENDING,
                'started_by' => $actor->id,
                'send_email' => $sendEmail,
                'send_sms' => $sendSms,
                'email_template' => $emailTemplate,
                'sms_template' => $smsTemplate,
                'check_ifirma' => (bool) ($options['check_ifirma'] ?? true),
                'attach_ifirma_pdf' => (bool) ($options['attach_ifirma_pdf'] ?? true),
                'strip_sms_diacritics' => (bool) ($options['strip_sms_diacritics'] ?? true),
                'sms_companion_email' => (bool) ($options['sms_companion_email'] ?? true),
                'selected_count' => count($selectedIds),
            ]);

            $jobs = [];
            foreach ($selectedIds as $caseId) {
                $item = DebtPaymentReminderCampaignItem::create([
                    'campaign_id' => $campaign->id,
                    'debt_case_id' => $caseId,
                    'status' => DebtPaymentReminderCampaignItem::STATUS_PENDING,
                ]);
                $jobs[] = new SendDebtPaymentReminderJob($item->id);
            }

            try {
                $batch = Bus::batch($jobs)
                    ->name('debt-payment-reminders-'.$campaign->uuid)
                    ->allowFailures()
                    ->finally(function () use ($campaign) {
                        $fresh = DebtPaymentReminderCampaign::query()->find($campaign->id);
                        if ($fresh === null) {
                            return;
                        }
                        if ($fresh->status === DebtPaymentReminderCampaign::STATUS_CANCELLED) {
                            return;
                        }
                        $fresh->update([
                            'status' => DebtPaymentReminderCampaign::STATUS_COMPLETED,
                        ]);
                    })
                    ->dispatch();
            } catch (Throwable $e) {
                $campaign->update([
                    'status' => DebtPaymentReminderCampaign::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);
                throw $e;
            }

            $campaign->update([
                'batch_id' => $batch->id,
                'status' => DebtPaymentReminderCampaign::STATUS_PROCESSING,
            ]);

            return $campaign->fresh();
        });
    }

    public function cancel(DebtPaymentReminderCampaign $campaign): void
    {
        if (! $campaign->isActive()) {
            return;
        }

        $campaign->update([
            'status' => DebtPaymentReminderCampaign::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        DebtPaymentReminderCampaignItem::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', DebtPaymentReminderCampaignItem::STATUS_PENDING)
            ->update([
                'status' => DebtPaymentReminderCampaignItem::STATUS_CANCELLED,
                'processed_at' => now(),
            ]);

        if ($campaign->batch_id) {
            try {
                $batch = Bus::findBatch($campaign->batch_id);
                $batch?->cancel();
            } catch (Throwable) {
                // ignore
            }
        }
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(DebtPaymentReminderCampaign $campaign): array
    {
        $counts = $campaign->items()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $keys = [
            DebtPaymentReminderCampaignItem::STATUS_PENDING,
            DebtPaymentReminderCampaignItem::STATUS_PROCESSING,
            DebtPaymentReminderCampaignItem::STATUS_SENT,
            DebtPaymentReminderCampaignItem::STATUS_SKIPPED_IFIRMA,
            DebtPaymentReminderCampaignItem::STATUS_SKIPPED_CASE,
            DebtPaymentReminderCampaignItem::STATUS_REQUIRES_REVIEW,
            DebtPaymentReminderCampaignItem::STATUS_FAILED,
            DebtPaymentReminderCampaignItem::STATUS_CANCELLED,
        ];

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = (int) ($counts[$key] ?? 0);
        }

        return $result;
    }

    public function canAttachSafeIfirmaPdf(DebtCase $case): bool
    {
        $case->loadMissing('formOrder');

        return filled($case->formOrder?->ifirma_invoice_id);
    }
}
