<?php

namespace App\Services;

use App\Models\DebtCase;
use App\Models\DebtCaseContact;
use App\Models\FormOrder;

class DebtReminderTemplateService
{
    public const TEMPLATE_REMINDER = 'reminder';

    public const TEMPLATE_DUNNING = 'dunning';

    /**
     * @return array<string, string>
     */
    public function templateLabels(): array
    {
        return [
            self::TEMPLATE_REMINDER => 'Przypomnienie o płatności',
            self::TEMPLATE_DUNNING => 'Ponaglenie (formalniejsze)',
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function build(DebtCase $case, string $template): array
    {
        $order = $case->formOrder;
        $invoice = $this->invoiceNumber($case, $order);
        $amount = $this->amountLabel($case, $order);
        $due = $this->dueDateLabel($case, $order);
        $bankLine = $this->bankAccountLine();
        $training = $this->trainingContext($order);

        return match ($template) {
            self::TEMPLATE_DUNNING => [
                'subject' => $this->subjectWithTraining(
                    'Ponaglenie — nieuregulowana faktura '.$invoice,
                    $training['title']
                ),
                'body' => $this->dunningBody($invoice, $amount, $due, $bankLine, $training),
            ],
            default => [
                'subject' => $this->subjectWithTraining(
                    'Przypomnienie o płatności — FV '.$invoice,
                    $training['title']
                ),
                'body' => $this->reminderBody($invoice, $amount, $due, $bankLine, $training),
            ],
        };
    }

    /**
     * @return list<array{key: string, label: string, email: string}>
     */
    public function recipientOptions(DebtCase $case): array
    {
        $order = $case->formOrder;
        $options = [];

        $ordererEmail = trim((string) ($order?->orderer_email ?? ''));
        if ($ordererEmail !== '' && filter_var($ordererEmail, FILTER_VALIDATE_EMAIL)) {
            $name = trim((string) ($order?->orderer_name ?? ''));
            $options[] = [
                'key' => 'orderer',
                'label' => 'Zamawiający'.($name !== '' ? ': '.$name : ''),
                'email' => $ordererEmail,
            ];
        }

        $participantEmail = trim((string) ($order?->display_participant_email ?? ''));
        if (
            $participantEmail !== ''
            && filter_var($participantEmail, FILTER_VALIDATE_EMAIL)
            && strcasecmp($participantEmail, $ordererEmail) !== 0
        ) {
            $name = trim((string) ($order?->display_participant_name ?? ''));
            $options[] = [
                'key' => 'participant',
                'label' => 'Uczestnik'.($name !== '' ? ': '.$name : ''),
                'email' => $participantEmail,
            ];
        }

        $contacts = $case->relationLoaded('contacts')
            ? $case->contacts
            : $case->contacts()->orderBy('id')->get();

        foreach ($contacts as $contact) {
            if ($contact->contact_type !== DebtCaseContact::TYPE_EMAIL) {
                continue;
            }
            $email = trim((string) $contact->value);
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $label = trim((string) ($contact->label ?: $contact->source ?: 'Kontakt'));
            $options[] = [
                'key' => 'contact:'.$contact->id,
                'label' => 'Kontakt sprawy: '.$label,
                'email' => $email,
            ];
        }

        return $options;
    }

    public function canAttachIfirmaPdf(DebtCase $case): bool
    {
        $order = $case->formOrder;

        return filled($order?->ifirma_invoice_id) || filled($this->invoiceNumber($case, $order));
    }

    public function ifirmaPdfLookupKey(DebtCase $case): ?string
    {
        $order = $case->formOrder;
        if (filled($order?->ifirma_invoice_id)) {
            return (string) $order->ifirma_invoice_id;
        }

        $invoice = $this->invoiceNumber($case, $order);
        if ($invoice === '—' || $invoice === '') {
            return null;
        }

        return str_replace('/', '_', $invoice);
    }

    private function invoiceNumber(DebtCase $case, ?FormOrder $order): string
    {
        $invoice = trim((string) ($case->invoice_number ?: $order?->invoice_number ?: ''));

        return $invoice !== '' ? $invoice : '—';
    }

    private function amountLabel(DebtCase $case, ?FormOrder $order): string
    {
        $amount = (float) ($case->amount_gross ?? $order?->product_price ?? 0);

        return number_format($amount, 2, ',', ' ');
    }

    private function dueDateLabel(DebtCase $case, ?FormOrder $order): string
    {
        if ($case->due_date) {
            return $case->due_date->format('d.m.Y');
        }

        if ($order?->order_date && $order->invoice_payment_delay) {
            return $order->order_date->copy()->addDays((int) $order->invoice_payment_delay)->format('d.m.Y');
        }

        return '—';
    }

    private function bankAccountLine(): string
    {
        $account = trim((string) config('services.ifirma.bank_account', ''));
        if ($account === '') {
            return '';
        }

        return 'Numer konta do przelewu: '.$account;
    }

    /**
     * @return array{title: string, start: string, instructor: string}
     */
    private function trainingContext(?FormOrder $order): array
    {
        $course = $order?->course;
        if ($course !== null && ! $course->relationLoaded('instructor')) {
            $course->loadMissing('instructor');
        }

        $title = $course
            ? $course->plainTitle((string) ($order->product_name ?: 'Szkolenie'))
            : trim((string) ($order?->product_name ?? ''));
        if ($title === '') {
            $title = '—';
        }

        $start = '—';
        if ($course?->start_date) {
            $start = $course->start_date
                ->timezone(config('app.timezone'))
                ->format('d.m.Y H:i');
        }

        $instructor = trim(
            ($course?->instructor?->first_name ?? '').' '.($course?->instructor?->last_name ?? '')
        );
        if ($instructor === '') {
            $instructor = '—';
        }

        return [
            'title' => $title,
            'start' => $start,
            'instructor' => $instructor,
        ];
    }

    private function subjectWithTraining(string $base, string $trainingTitle): string
    {
        $suffix = ' — SZKOLENIE: '.$trainingTitle;
        $subject = $base.$suffix;
        if (mb_strlen($subject) <= 255) {
            return $subject;
        }

        $maxTitle = max(20, 255 - mb_strlen($base.' — SZKOLENIE: ') - 1);

        return $base.' — SZKOLENIE: '.mb_substr($trainingTitle, 0, $maxTitle).'…';
    }

    /**
     * @param  array{title: string, start: string, instructor: string}  $training
     * @return list<string>
     */
    private function trainingBodyLines(array $training): array
    {
        return [
            'Dotyczy szkolenia:',
            'Temat: '.$training['title'],
            'Data startu: '.$training['start'],
            'Prowadzący: '.$training['instructor'],
            '',
        ];
    }

    /**
     * @param  array{title: string, start: string, instructor: string}  $training
     */
    private function reminderBody(string $invoice, string $amount, string $due, string $bankLine, array $training): string
    {
        $lines = [
            'Dzień dobry,',
            '',
            'uprzejmie przypominamy o płatności za fakturę '.$invoice,
            'na kwotę '.$amount.' zł (termin: '.$due.').',
            '',
            ...$this->trainingBodyLines($training),
        ];

        if ($bankLine !== '') {
            $lines[] = $bankLine;
            $lines[] = '';
        }

        $lines = array_merge($lines, [
            'Jeśli przelew został już zlecony — dziękujemy, prosimy o zignorowanie tej wiadomości.',
            'W razie pytań lub potrzeby potwierdzenia danych do przelewu — prosimy o kontakt.',
            '',
            ...$this->signatureLines(),
        ]);

        return implode("\n", $lines);
    }

    /**
     * @param  array{title: string, start: string, instructor: string}  $training
     */
    private function dunningBody(string $invoice, string $amount, string $due, string $bankLine, array $training): string
    {
        $lines = [
            'Dzień dobry,',
            '',
            'informujemy, że faktura '.$invoice.' na kwotę '.$amount.' zł',
            'z terminem płatności '.$due.' pozostaje nieuregulowana.',
            '',
            ...$this->trainingBodyLines($training),
        ];

        if ($bankLine !== '') {
            $lines[] = $bankLine;
            $lines[] = '';
        }

        $lines = array_merge($lines, [
            'Prosimy o niezwłoczne uregulowanie należności lub kontakt w celu wyjaśnienia sprawy.',
            'W załączeniu przesyłamy fakturę (jeśli została dołączona).',
            '',
            ...$this->signatureLines(),
        ]);

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function signatureLines(): array
    {
        $lines = [
            'Z poważaniem,',
            'Zespół Platformy Nowoczesnej Edukacji',
            'kontakt@pnedu.pl',
        ];

        $phone = \App\Models\DebtCollectionSetting::contactPhone();
        if ($phone !== null) {
            $lines[] = 'tel. '.$phone;
        }

        return $lines;
    }
}
