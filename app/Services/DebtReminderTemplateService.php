<?php

namespace App\Services;

use App\Models\DebtCase;
use App\Models\DebtCaseContact;
use App\Models\FormOrder;

class DebtReminderTemplateService
{
    public const TEMPLATE_REMINDER = 'reminder';

    public const TEMPLATE_DUNNING = 'dunning';

    public const INVOICE_ATTACHMENT_SENTENCE = 'W załączeniu przesyłamy fakturę.';

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

    /**
     * Czy można wstawić do treści link do publicznego PDF potwierdzenia zamówienia (pnedu).
     */
    public function canIncludeOrderConfirmationLink(DebtCase $case): bool
    {
        return $this->orderConfirmationPdfUrl($case) !== null;
    }

    /**
     * Publiczny URL PDF potwierdzenia zamówienia na pnedu (ten sam co po złożeniu zamówienia).
     */
    public function orderConfirmationPdfUrl(DebtCase $case): ?string
    {
        $order = $case->formOrder;
        $ident = trim((string) ($order?->ident ?? ''));
        if ($ident === '') {
            return null;
        }

        $base = rtrim((string) config('services.pnedu_frontend_url', ''), '/');
        if ($base === '') {
            return null;
        }

        return $base.'/orders/'.rawurlencode($ident).'/pdf';
    }

    /**
     * Blok tekstu do treści e-maila (plain text; URL klikalny przez linkify).
     */
    public function orderConfirmationBodyBlock(DebtCase $case): ?string
    {
        $url = $this->orderConfirmationPdfUrl($case);
        $order = $case->formOrder;
        if ($url === null || $order === null) {
            return null;
        }

        return 'Faktura została wystawiona na podstawie zamówienia #'.$order->id
            .', pobierz potwierdzenie zamówienia: '.$url;
    }

    /**
     * Synchronizuje blok linku w treści wg checkboxa. Brak ident/URL → bez zmian (pomijamy).
     * Blok wstawiany nad podpisem („Z poważaniem,”), nie na samym końcu.
     */
    public function syncOrderConfirmationLinkInBody(string $body, DebtCase $case, bool $include): string
    {
        $block = $this->orderConfirmationBodyBlock($case);
        $url = $this->orderConfirmationPdfUrl($case);
        if ($block === null || $url === null) {
            return $body;
        }

        $stripped = $this->stripOrderConfirmationBlock($body, $url);
        if (! $include) {
            return $stripped;
        }

        return $this->insertBlockBeforeSignature($stripped, $block);
    }

    /**
     * Zdanie o załączonej FV — gdy faktycznie dołączono PDF faktury (przypomnienie lub ponaglenie).
     * Usuwa też starą wersję z dopiskiem „(jeśli została dołączona)”.
     */
    public function syncInvoiceAttachmentSentenceInBody(string $body, bool $include): string
    {
        $stripped = $this->stripInvoiceAttachmentSentence($body);
        if (! $include) {
            return $stripped;
        }

        return $this->insertBlockBeforeSignature($stripped, self::INVOICE_ATTACHMENT_SENTENCE);
    }

    private function stripInvoiceAttachmentSentence(string $body): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $body);
        $pattern = '/(?:\n{0,2})W załączeniu przesyłamy fakturę(?: \(jeśli została dołączona\))?\.?/u';
        $cleaned = preg_replace($pattern, '', $normalized);

        return is_string($cleaned) ? rtrim($cleaned) : rtrim($body);
    }

    private function insertBlockBeforeSignature(string $body, string $block): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", rtrim($body));
        $needle = "\nZ poważaniem,";
        $pos = mb_strrpos($normalized, $needle);
        if ($pos === false && str_starts_with($normalized, 'Z poważaniem,')) {
            return $block."\n\n".$normalized;
        }
        if ($pos === false) {
            return $normalized."\n\n".$block;
        }

        return rtrim(mb_substr($normalized, 0, $pos))."\n\n".$block."\n\n".ltrim(mb_substr($normalized, $pos), "\n");
    }

    private function stripOrderConfirmationBlock(string $body, string $url): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $body);
        $escapedUrl = preg_quote($url, '/');
        // Usuń blok (URL w tej samej linii albo w następnym wierszu — stary format)
        $pattern = '/(?:\n{0,2})Faktura została wystawiona na podstawie zamówienia #\d+,\s*'
            .'pobierz potwierdzenie zamówienia:\s*\n?'.$escapedUrl.'/u';
        $cleaned = preg_replace($pattern, '', $normalized);
        if (! is_string($cleaned)) {
            return rtrim($body);
        }

        // Gdy w treści został sam URL (ręczna edycja) — też usuń linię z URL
        $cleaned = preg_replace('/\n?'.preg_quote($url, '/').'(?=\n|$)/u', '', $cleaned);

        return is_string($cleaned) ? rtrim($cleaned) : rtrim($body);
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

        if ($order?->invoice_due_date) {
            return $order->invoice_due_date->format('d.m.Y');
        }

        $dueBase = $order?->invoice_issue_date?->copy() ?? $order?->order_date?->copy();
        if ($dueBase && $order?->invoice_payment_delay) {
            return $dueBase->copy()->addDays((int) $order->invoice_payment_delay)->format('d.m.Y');
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
            'Data szkolenia: '.$training['start'],
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
