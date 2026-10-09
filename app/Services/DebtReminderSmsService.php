<?php

namespace App\Services;

use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Services\Sms\SmsapiClient;
use Illuminate\Support\Facades\Auth;

class DebtReminderSmsService
{
    public function __construct(
        private readonly SmsapiClient $smsapi,
        private readonly DebtReminderTemplateService $templates,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function send(
        DebtCase $case,
        string $toPhone,
        string $body,
        bool $isTest,
    ): array {
        $normalized = $this->templates->normalizePhoneToSmsapi($toPhone);
        if ($normalized === null) {
            return [
                'ok' => false,
                'message' => 'Podaj prawidłowy numer telefonu PL (9 cyfr lub +48…).',
            ];
        }

        $message = trim($body);
        if ($message === '') {
            return [
                'ok' => false,
                'message' => 'Treść SMS nie może być pusta.',
            ];
        }

        if (mb_strlen($message) > 600) {
            return [
                'ok' => false,
                'message' => 'Treść SMS jest zbyt długa (max 600 znaków).',
            ];
        }

        $result = $this->smsapi->sendSms($normalized, $message);
        if (! $result['ok']) {
            return [
                'ok' => false,
                'message' => $result['message'],
            ];
        }

        $displayPhone = $this->templates->formatPhoneDisplay($normalized);
        $this->logAction($case, $displayPhone, $message, $isTest, $result['message_id'] ?? null);

        $partsHint = $this->templates->estimateSmsParts($message);
        $suffix = $partsHint > 1
            ? sprintf(' (ok. %d części SMS)', $partsHint)
            : '';

        return [
            'ok' => true,
            'message' => $isTest
                ? 'Wysłano SMS testowy na '.$displayPhone.$suffix.'.'
                : 'Wysłano SMS przypomnienia na '.$displayPhone.$suffix.'.',
        ];
    }

    private function logAction(
        DebtCase $case,
        string $toLabel,
        string $body,
        bool $isTest,
        ?string $messageId,
    ): void {
        $parts = [
            $isTest ? '[TEST]' : '[WYSYŁKA]',
            'Do: '.$toLabel,
            'Treść: '.mb_substr($body, 0, 200).(mb_strlen($body) > 200 ? '…' : ''),
        ];
        if ($messageId) {
            $parts[] = 'SMSAPI id: '.$messageId;
        }

        $case->actions()->create([
            'user_id' => Auth::id(),
            'action_type' => DebtCaseAction::TYPE_SMS,
            'channel' => 'sms',
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
