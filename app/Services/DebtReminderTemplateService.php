<?php

namespace App\Services;

use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\DebtCaseContact;
use App\Models\DebtCollectionSetting;
use App\Models\FormOrder;

class DebtReminderTemplateService
{
    public const TEMPLATE_REMINDER = 'reminder';

    public const TEMPLATE_DUNNING = 'dunning';

    /** Uprzejme zapytanie o płatność — dla klientów VIP. */
    public const TEMPLATE_VIP = 'vip_soft';

    /** Zdanie o PDF dla szablonu ponaglenia (bez zmian). */
    public const INVOICE_ATTACHMENT_SENTENCE = 'W załączeniu przesyłamy fakturę.';

    /** Zdanie o PDF dla przypomnienia — bez wcześniejszej wysyłki w historii. */
    public const INVOICE_ATTACHMENT_SENTENCE_REMINDER = 'Dla wygody załączamy fakturę do tej wiadomości w pliku PDF.';

    /** Zdanie o PDF dla przypomnienia — gdy historia potwierdza wcześniejszą wysyłkę FV. */
    public const INVOICE_ATTACHMENT_SENTENCE_REMINDER_AGAIN = 'Dla wygody ponownie załączamy ją do tej wiadomości w pliku PDF.';

    public const CONTACT_EMAIL = 'kontakt@pnedu.pl';

    /**
     * @return array<string, string>
     */
    public function templateLabels(): array
    {
        return [
            self::TEMPLATE_REMINDER => 'Przypomnienie o płatności',
            self::TEMPLATE_VIP => 'VIP — uprzejme zapytanie o płatność',
            self::TEMPLATE_DUNNING => 'Ponaglenie (formalniejsze)',
        ];
    }

    /**
     * Domyślny szablon w modalu: VIP → uprzejme zapytanie, inaczej przypomnienie.
     */
    public function defaultTemplateForCase(DebtCase $case): string
    {
        return $case->isVip() ? self::TEMPLATE_VIP : self::TEMPLATE_REMINDER;
    }

    /**
     * @return array{subject: string, body: string, invoice_attachment_sentence: string}
     */
    public function build(DebtCase $case, string $template): array
    {
        $order = $case->formOrder;
        $invoice = $this->invoiceNumber($case, $order);
        $amount = $this->amountDueLabel($case, $order);
        $due = $this->dueDateLabel($case, $order);
        $bankLine = $this->bankAccountLine();
        $training = $this->trainingContext($order);
        $hadInvoiceEmail = $this->hasConfirmedInvoiceEmailSent($case);

        return match ($template) {
            self::TEMPLATE_DUNNING => [
                'subject' => $this->subjectWithTraining(
                    'Ponaglenie — nieuregulowana faktura '.$invoice,
                    $training['title']
                ),
                'body' => $this->dunningBody($invoice, $amount, $due, $bankLine, $training),
                'invoice_attachment_sentence' => self::INVOICE_ATTACHMENT_SENTENCE,
            ],
            self::TEMPLATE_VIP => [
                'subject' => 'Prośba o potwierdzenie płatności — FV '.$invoice.' — Platforma Nowoczesnej Edukacji',
                'body' => $this->vipSoftBody($order, $invoice, $amount, $due, $training, $hadInvoiceEmail),
                'invoice_attachment_sentence' => $this->reminderInvoiceAttachmentSentence($hadInvoiceEmail),
            ],
            default => [
                'subject' => 'Przypomnienie o płatności za fakturę '.$invoice.' — Platforma Nowoczesnej Edukacji',
                'body' => $this->reminderBody($case, $order, $invoice, $amount, $due, $training, $hadInvoiceEmail),
                'invoice_attachment_sentence' => $this->reminderInvoiceAttachmentSentence($hadInvoiceEmail),
            ],
        };
    }

    public function invoiceAttachmentSentenceForTemplate(string $template, DebtCase $case): string
    {
        if ($template === self::TEMPLATE_DUNNING) {
            return self::INVOICE_ATTACHMENT_SENTENCE;
        }

        return $this->reminderInvoiceAttachmentSentence($this->hasConfirmedInvoiceEmailSent($case));
    }

    public function reminderInvoiceAttachmentSentence(bool $hadConfirmedInvoiceEmail): string
    {
        return $hadConfirmedInvoiceEmail
            ? self::INVOICE_ATTACHMENT_SENTENCE_REMINDER_AGAIN
            : self::INVOICE_ATTACHMENT_SENTENCE_REMINDER;
    }

    /**
     * Właściwa wysyłka e-maila z załącznikiem FV (nie test, nie nieudana).
     */
    public function hasConfirmedInvoiceEmailSent(DebtCase $case): bool
    {
        return $case->actions()
            ->where('action_type', DebtCaseAction::TYPE_EMAIL)
            ->where('outcome', 'sent')
            ->where('note', 'like', '%Załączniki:%')
            ->exists();
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
     * Usuwa też starsze warianty zdań.
     */
    public function syncInvoiceAttachmentSentenceInBody(
        string $body,
        bool $include,
        ?string $sentence = null,
    ): string {
        $stripped = $this->stripInvoiceAttachmentSentence($body);
        if (! $include) {
            return $stripped;
        }

        return $this->insertInvoiceAttachmentSentence(
            $stripped,
            $sentence ?? self::INVOICE_ATTACHMENT_SENTENCE
        );
    }

    private function stripInvoiceAttachmentSentence(string $body): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $body);
        $patterns = [
            '/(?:\n{0,2})W załączeniu przesyłamy fakturę(?: \(jeśli została dołączona\))?\.?/u',
            '/(?:\n{0,2})?Dla wygody ponownie załączamy ją do tej wiadomości w pliku PDF\.?\s*/u',
            '/(?:\n{0,2})?Dla wygody załączamy fakturę do tej wiadomości w pliku PDF\.?\s*/u',
        ];
        $cleaned = $normalized;
        foreach ($patterns as $pattern) {
            $next = preg_replace($pattern, '', $cleaned);
            if (is_string($next)) {
                $cleaned = $next;
            }
        }

        return rtrim(preg_replace('/[ \t]+\n/', "\n", $cleaned) ?? $cleaned);
    }

    private function insertInvoiceAttachmentSentence(string $body, string $sentence): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", rtrim($body));
        $paymentNeedle = 'Prosimy o uregulowanie należności na rachunek wskazany na fakturze.';
        $pos = mb_strpos($normalized, $paymentNeedle);
        if ($pos !== false) {
            $before = rtrim(mb_substr($normalized, 0, $pos));
            $after = mb_substr($normalized, $pos);
            $spacer = $before === '' || str_ends_with($before, '.') || str_ends_with($before, "\n")
                ? ($before !== '' && ! str_ends_with($before, "\n") ? ' ' : '')
                : ' ';

            return $before.$spacer.$sentence.' '.$after;
        }

        return $this->insertBlockBeforeSignature($normalized, $sentence);
    }

    private function insertBlockBeforeSignature(string $body, string $block): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", rtrim($body));
        foreach (["\nPozdrawiam\n", "\nZ poważaniem,"] as $needle) {
            $pos = mb_strrpos($normalized, $needle);
            if ($pos !== false) {
                return rtrim(mb_substr($normalized, 0, $pos))."\n\n".$block."\n\n".ltrim(mb_substr($normalized, $pos), "\n");
            }
        }
        if (str_starts_with($normalized, 'Pozdrawiam') || str_starts_with($normalized, 'Z poważaniem,')) {
            return $block."\n\n".$normalized;
        }

        return $normalized."\n\n".$block;
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

    /**
     * Aktualne saldo do zapłaty (kwota FV minus zaakceptowane wpłaty z wyciągu).
     */
    private function amountDueLabel(DebtCase $case, ?FormOrder $order): string
    {
        $remaining = $case->remainingBankAllocatableAmount();
        if ($remaining !== null) {
            return number_format($remaining, 2, ',', ' ');
        }

        return $this->amountLabel($case, $order);
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
        $account = $this->resolvedCompanyBankAccountDisplay();
        if ($account === '') {
            return '';
        }

        return 'Numer konta do przelewu: '.$account;
    }

    /**
     * IBAN z Zakupy pnedu.pl; fallback: IFIRMA_BANK_ACCOUNT z .env.
     */
    public function resolvedCompanyBankAccountCompact(): ?string
    {
        $fromSettings = \App\Models\PaymentDisplayOption::companyBankAccount();
        if ($fromSettings !== null) {
            return $fromSettings;
        }

        return \App\Models\PaymentDisplayOption::normalizeCompanyBankAccount(
            (string) config('services.ifirma.bank_account', '')
        );
    }

    public function resolvedCompanyBankAccountDisplay(): string
    {
        $compact = $this->resolvedCompanyBankAccountCompact();
        if ($compact === null) {
            return '';
        }

        return \App\Models\PaymentDisplayOption::formatCompanyBankAccount($compact);
    }

    /**
     * Fragment SMS z numerem konta (kompaktowy IBAN — krócej).
     * Przykład: ` Konto:PL61109010140000071219812874`
     */
    public function smsBankSnippet(): ?string
    {
        $compact = $this->resolvedCompanyBankAccountCompact();
        if ($compact === null) {
            return null;
        }

        return ' Konto:'.$compact;
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
    private function reminderBody(
        DebtCase $case,
        ?FormOrder $order,
        string $invoice,
        string $amount,
        string $due,
        array $training,
        bool $hadConfirmedInvoiceEmail,
    ): string {
        $title = trim($training['title']);
        $hasTitle = $title !== '' && $title !== '—';

        $opening = $hasTitle
            ? 'nie odnotowaliśmy dotychczas wpłaty za szkolenie „'.$title.'”, którego dotyczy faktura nr '.$invoice.'.'
            : 'nie odnotowaliśmy dotychczas wpłaty za szkolenie, którego dotyczy faktura nr '.$invoice.'.';

        $ordererName = trim((string) ($order?->orderer_name ?? ''));
        $participantName = trim((string) ($order?->display_participant_name ?? ''));
        $bankAccount = $this->resolvedCompanyBankAccountDisplay();
        $dueStatus = $this->paymentDueStatusLabel($case, $order);

        $costLine = 'Koszt: '.$amount.' zł (termin płatności: '.$due
            .($dueStatus !== null ? ' — '.$dueStatus : '')
            .')';

        // Needle używany też przez syncInvoiceAttachmentSentenceInBody / JS w modalu.
        $settlement = $hadConfirmedInvoiceEmail
            ? 'Fakturę przesłaliśmy wcześniej e-mailem. Prosimy o uregulowanie należności na rachunek wskazany na fakturze.'
            : 'Prosimy o uregulowanie należności na rachunek wskazany na fakturze.';

        $lines = [
            'Dzień dobry,',
            '',
            $opening,
            '',
            'SZKOLENIE:',
            'Temat: '.($hasTitle ? $title : '—'),
            'Termin: '.$training['start'],
            'Prowadzący: '.$training['instructor'],
            'Uczestnik: '.($participantName !== '' ? $participantName : '—'),
            'Zamawiający: '.($ordererName !== '' ? $ordererName : '—'),
            '',
            'Dane do przelewu:',
            $costLine,
            'Tytuł przelewu: '.$invoice,
            'Nr konta: '.($bankAccount !== '' ? $bankAccount : '—'),
            '',
            $settlement,
            '',
            'Jeżeli płatność została już wykonana, prosimy o odpowiedź z potwierdzeniem i datą przelewu — pozwoli nam to sprawdzić i wyjaśnić rozliczenie.',
            '',
            'Jeżeli faktura budzi wątpliwości lub wystąpiła przeszkoda w dokonaniu płatności, prosimy o kontakt i wskazanie przewidywanego terminu zapłaty.',
            '',
            'Autentyczność wiadomości można potwierdzić, korzystając z danych kontaktowych opublikowanych na naszej stronie pnedu.pl.',
            '',
            ...$this->reminderSignatureLines(),
        ];

        return implode("\n", $lines);
    }

    /**
     * Dyplomatyczna treść dla VIP: prośba o potwierdzenie, bez tonu monitu.
     *
     * @param  array{title: string, start: string, instructor: string}  $training
     */
    private function vipSoftBody(
        ?FormOrder $order,
        string $invoice,
        string $amount,
        string $due,
        array $training,
        bool $hadConfirmedInvoiceEmail,
    ): string {
        $title = trim($training['title']);
        $hasTitle = $title !== '' && $title !== '—';

        $opening = $hasTitle
            ? 'piszę w sprawie faktury nr '.$invoice.' dotyczącej szkolenia „'.$title.'”.'
            : 'piszę w sprawie faktury nr '.$invoice.'.';

        $ordererName = trim((string) ($order?->orderer_name ?? ''));
        $participantName = trim((string) ($order?->display_participant_name ?? ''));
        $bankAccount = $this->resolvedCompanyBankAccountDisplay();

        $costLine = 'Koszt: '.$amount.' zł (termin płatności: '.$due.')';

        // Needle jak w przypomnieniu — sync zdania o PDF w modalu / backendzie.
        $settlement = $hadConfirmedInvoiceEmail
            ? 'Fakturę przesłaliśmy wcześniej e-mailem. Prosimy o uregulowanie należności na rachunek wskazany na fakturze.'
            : 'Prosimy o uregulowanie należności na rachunek wskazany na fakturze.';

        $lines = [
            'Dzień dobry,',
            '',
            $opening,
            '',
            'W naszych systemach nie możemy obecnie zidentyfikować wpłaty odpowiadającej tej fakturze — stąd uprzejma prośba o krótkie potwierdzenie.',
            '',
            'SZKOLENIE:',
            'Temat: '.($hasTitle ? $title : '—'),
            'Termin: '.$training['start'],
            'Prowadzący: '.$training['instructor'],
            'Uczestnik: '.($participantName !== '' ? $participantName : '—'),
            'Zamawiający: '.($ordererName !== '' ? $ordererName : '—'),
            '',
            'Dane do przelewu:',
            $costLine,
            'Tytuł przelewu: '.$invoice,
            'Nr konta: '.($bankAccount !== '' ? $bankAccount : '—'),
            '',
            $settlement,
            '',
            'Jeśli przelew został już zlecony, wystarczy odpowiedź z potwierdzeniem i datą — dzięki temu szybko dopasujemy wpłatę i domkniemy rozliczenie.',
            '',
            'Jeżeli faktura lub kwota budzą wątpliwości, prosimy o kontakt — chętnie pomożemy.',
            '',
            'Z góry dziękuję za współpracę.',
            '',
            ...$this->reminderSignatureLines(),
        ];

        return implode("\n", $lines);
    }

    /**
     * Status terminu płatności względem dziś (do linii Koszt; „po terminie” wyróżniane na czerwono w HTML).
     */
    public function paymentDueStatusLabel(DebtCase $case, ?FormOrder $order = null): ?string
    {
        $order ??= $case->formOrder;
        $due = $case->due_date?->copy()->timezone(config('app.timezone'))->startOfDay()
            ?? $order?->invoice_due_date?->copy()->timezone(config('app.timezone'))->startOfDay();
        if ($due === null) {
            return null;
        }

        $today = now()->timezone(config('app.timezone'))->startOfDay();
        if ($due->lt($today)) {
            $days = (int) $due->diffInDays($today);

            return $days.' '.$this->polishDayWord($days).' po terminie';
        }
        if ($due->equalTo($today)) {
            return 'termin dziś';
        }

        $days = (int) $today->diffInDays($due);

        return 'za '.$days.' '.$this->polishDayWord($days);
    }

    private function polishDayWord(int $n): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        if ($n === 1) {
            return 'dzień';
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ! ($mod100 >= 12 && $mod100 <= 14)) {
            return 'dni';
        }

        return 'dni';
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
            self::CONTACT_EMAIL,
        ];

        $phone = DebtCollectionSetting::contactPhone();
        if ($phone !== null) {
            $lines[] = 'tel. '.$phone;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function reminderSignatureLines(): array
    {
        $lines = [
            'Pozdrawiam',
            'Waldemar Grabowski',
            'Platforma Nowoczesnej Edukacji',
            self::CONTACT_EMAIL,
        ];

        $phone = DebtCollectionSetting::contactPhone();
        if ($phone !== null) {
            $lines[] = 'tel. '.$phone;
        }

        return $lines;
    }

    /**
     * Telefon kontaktowy PNE do SMS (bez prefiksu +48, jak w przykładzie biznesowym).
     */
    public function smsContactPhoneLabel(): ?string
    {
        $phone = DebtCollectionSetting::contactPhone();
        if ($phone === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $phone) ?? $phone);
        $withoutCountry = preg_replace('/^\+?\s*48\s*/u', '', $normalized);
        $withoutCountry = is_string($withoutCountry) ? trim($withoutCountry) : $normalized;

        return $withoutCountry !== '' ? $withoutCountry : $normalized;
    }

    /**
     * Telefon zamawiającego do SMS (MVP: tylko ten numer).
     *
     * @return array{phone: string, label: string, digits: string}|null
     */
    public function ordererSmsRecipient(DebtCase $case): ?array
    {
        $order = $case->formOrder;
        $raw = trim((string) ($order?->orderer_phone ?? ''));
        $digits = $this->normalizePhoneToSmsapi($raw);
        if ($digits === null) {
            return null;
        }

        $name = trim((string) ($order?->orderer_name ?? ''));

        return [
            'phone' => $this->formatPhoneDisplay($digits),
            'label' => 'Zamawiający'.($name !== '' ? ': '.$name : ''),
            'digits' => $digits,
        ];
    }

    /**
     * @deprecated Skrót tytułu jest w treści SMS; snippet opcjonalny usunięty z UI.
     */
    public function smsTrainingSnippet(DebtCase $case, int $maxTitleChars = 40): ?string
    {
        return null;
    }

    /**
     * Marker FV w treści SMS (np. pod checkbox nr konta / legacy JS).
     */
    public function smsInvoiceMarker(DebtCase $case): string
    {
        return 'FV '.$this->invoiceNumber($case, $case->formOrder);
    }

    public function shortenSmsTitle(string $title, int $maxChars = 40): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
        if ($title === '') {
            return '';
        }

        if (mb_strlen($title) <= $maxChars) {
            return $title;
        }

        $cut = max(1, $maxChars - 3);

        return rtrim(mb_substr($title, 0, $cut)).'...';
    }

    /**
     * Skrót tematu do SMS: 2 pierwsze wyrazy (+ trzeci, gdy drugi to krótki przyimek, np. „na”),
     * potem „...”. Przykład: „Konkurs na dyrektora...”.
     */
    public function smsShortTrainingTitle(string $title, int $wordCount = 2): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
        $title = rtrim($title, " \t.");
        if ($title === '' || $title === '—') {
            return '';
        }

        $words = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false || $words === []) {
            return '';
        }

        $take = min($wordCount, count($words));
        $shortPreps = ['na', 'i', 'w', 'o', 'z', 'do', 'od', 'we', 'ze', 'u', 'a', 'oraz', 'dla', 'po'];
        if (
            $take >= 2
            && isset($words[$take - 1], $words[$take])
            && in_array(mb_strtolower($words[$take - 1]), $shortPreps, true)
        ) {
            $take++;
        }

        $short = implode(' ', array_slice($words, 0, $take));
        if ($take >= count($words)) {
            return $short;
        }

        return $short.'...';
    }

    /**
     * Szablony SMS. Nr konta — opcjonalny checkbox w UI (domyślnie OFF).
     * Skrót tematu szkolenia jest w treści przypomnienia.
     *
     * @return array{body: string, invoice_marker: string, training_snippet: ?string, bank_snippet: ?string}
     */
    public function buildSms(DebtCase $case, string $template): array
    {
        $order = $case->formOrder;
        $invoice = $this->invoiceNumber($case, $order);
        $amount = $this->amountDueLabel($case, $order);
        $due = $this->dueDateLabel($case, $order);

        $body = match ($template) {
            self::TEMPLATE_DUNNING => $this->dunningSmsBody($case, $invoice, $amount, $due),
            self::TEMPLATE_VIP => $this->vipSoftSmsBody($case, $order, $invoice, $amount),
            default => $this->reminderSmsBody($case, $order, $invoice, $amount),
        };

        return [
            'body' => $body,
            'invoice_marker' => 'FV '.$invoice,
            'training_snippet' => null,
            'bank_snippet' => $this->smsBankSnippet(),
        ];
    }

    private function reminderSmsBody(DebtCase $case, ?FormOrder $order, string $invoice, string $amount): string
    {
        if ($order !== null && ! $order->relationLoaded('course')) {
            $order->loadMissing('course');
        }

        $shortTitle = $this->smsShortTrainingTitle($this->trainingContext($order)['title']);
        if ($shortTitle !== '') {
            $opening = 'Platforma Nowoczesnej Edukacji: nie odnotowaliśmy wpłaty '.$amount.' zł za szkolenie: '.$shortTitle;
            if (! str_ends_with($shortTitle, '...')) {
                $opening .= '.';
            }
        } else {
            $opening = 'Platforma Nowoczesnej Edukacji: nie odnotowaliśmy wpłaty '.$amount.' zł za szkolenie.';
        }

        $parts = [$opening];

        if ($this->hasConfirmedInvoiceEmailSent($case)) {
            $parts[] = 'Fakturę FV '.$invoice.' wysłaliśmy e-mailem.';
        } else {
            $parts[] = 'Dotyczy FV '.$invoice.'.';
        }

        $parts[] = 'Prosimy o zapłatę na rachunek z faktury.';

        $phone = $this->smsContactPhoneLabel();
        if ($phone !== null) {
            $parts[] = 'Kontakt: '.$phone.'.';
        }

        return implode(' ', $parts);
    }

    private function dunningSmsBody(DebtCase $case, string $invoice, string $amount, string $due): string
    {
        $parts = [
            'Platforma Nowoczesnej Edukacji: ponaglenie — faktura FV '.$invoice
                .' na '.$amount.' zł pozostaje nieuregulowana (termin płatności '.$due.').',
        ];

        if ($this->hasConfirmedInvoiceEmailSent($case)) {
            $parts[] = 'Ponownie wysłaliśmy e-mail z fakturą.';
        } else {
            $parts[] = 'Wysłaliśmy e-mail z fakturą.';
        }

        $parts[] = 'Prosimy o pilną zapłatę na rachunek wskazany na fakturze.';

        $phone = $this->smsContactPhoneLabel();
        if ($phone !== null) {
            $parts[] = 'Kontakt: '.$phone.'.';
        }

        return implode(' ', $parts);
    }

    private function vipSoftSmsBody(DebtCase $case, ?FormOrder $order, string $invoice, string $amount): string
    {
        if ($order !== null && ! $order->relationLoaded('course')) {
            $order->loadMissing('course');
        }

        $shortTitle = $this->smsShortTrainingTitle($this->trainingContext($order)['title']);
        $opening = 'Platforma Nowoczesnej Edukacji: uprzejma prośba o potwierdzenie płatności '
            .$amount.' zł za FV '.$invoice;
        if ($shortTitle !== '') {
            $opening .= ' ('.$shortTitle.')';
        }
        $opening .= '.';

        $parts = [
            $opening,
            'Nie możemy zidentyfikować wpłaty — prosimy o krótką odpowiedź z datą przelewu.',
        ];

        $phone = $this->smsContactPhoneLabel();
        if ($phone !== null) {
            $parts[] = 'Kontakt: '.$phone.'.';
        }

        return implode(' ', $parts);
    }

    /**
     * Numery testowe z konfiguracji (do wyboru w UI).
     *
     * @return list<array{phone: string, digits: string, email: ?string}>
     */
    public function smsTestPhoneOptions(): array
    {
        $options = [];
        foreach ((array) config('services.smsapi.test_phones', []) as $raw) {
            $digits = $this->normalizePhoneToSmsapi((string) $raw);
            if ($digits === null) {
                continue;
            }
            $options[] = [
                'phone' => $this->formatPhoneDisplay($digits),
                'digits' => $digits,
                'email' => $this->testEmailForSmsPhone($digits),
            ];
        }

        return $options;
    }

    public function testEmailForSmsPhone(string $digitsOrRaw): ?string
    {
        $digits = $this->normalizePhoneToSmsapi($digitsOrRaw);
        if ($digits === null) {
            return null;
        }

        $map = (array) config('services.smsapi.test_phone_emails', []);
        $email = trim((string) ($map[$digits] ?? ''));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Odbiorcy e-maila towarzyszącego SMS (zamawiający + uczestnik, jeśli różne).
     *
     * @return list<string>
     */
    public function companionEmailRecipients(DebtCase $case): array
    {
        $emails = [];
        foreach ($this->recipientOptions($case) as $option) {
            if (! in_array($option['key'], ['orderer', 'participant'], true)) {
                continue;
            }
            $emails[] = $option['email'];
        }

        return array_values(array_unique($emails));
    }

    /**
     * Treść e-maila przypomnienia do wysyłki razem z SMS (z zdaniem o PDF iFirma).
     *
     * @return array{subject: string, body: string, recipients: list<string>}
     */
    public function buildCompanionReminderEmail(DebtCase $case, bool $isTest, ?string $testPhoneDigits = null): array
    {
        $payload = $this->build($case, self::TEMPLATE_REMINDER);
        $body = $this->syncInvoiceAttachmentSentenceInBody(
            $payload['body'],
            true,
            $payload['invoice_attachment_sentence']
        );

        if ($isTest) {
            $email = $testPhoneDigits !== null ? $this->testEmailForSmsPhone($testPhoneDigits) : null;
            $recipients = $email !== null ? [$email] : [];
        } else {
            $recipients = $this->companionEmailRecipients($case);
        }

        return [
            'subject' => $payload['subject'],
            'body' => $body,
            'recipients' => $recipients,
        ];
    }

    /**
     * Normalizacja do formatu SMSAPI: 48xxxxxxxxx (bez +).
     */
    public function normalizePhoneToSmsapi(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0048')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 9) {
            $digits = '48'.$digits;
        }

        if (! preg_match('/^48\d{9}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    public function formatPhoneDisplay(string $digitsOrRaw): string
    {
        $digits = $this->normalizePhoneToSmsapi($digitsOrRaw) ?? preg_replace('/\D+/', '', $digitsOrRaw);
        if (is_string($digits) && preg_match('/^48(\d{3})(\d{3})(\d{3})$/', $digits, $m)) {
            return '+48 '.$m[1].' '.$m[2].' '.$m[3];
        }

        return trim($digitsOrRaw);
    }

    /**
     * Szacunek liczby części SMS (GSM-7 vs UCS-2 z polskimi znakami).
     */
    public function estimateSmsParts(string $message): int
    {
        $length = mb_strlen($message);
        if ($length === 0) {
            return 0;
        }

        $hasUnicode = (bool) preg_match('/[^\x00-\x7F]/u', $message);
        if ($hasUnicode) {
            // UCS-2: 70 / 67
            return $length <= 70 ? 1 : (int) ceil($length / 67);
        }

        // GSM-7: 160 / 153
        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }

    /**
     * Transliteracja polskich znaków i typowych „smart” znaków do ASCII,
     * żeby SMS mógł iść w GSM-7 (160/153 zamiast 70/67 znaków na część).
     */
    public function toGsmFriendlySms(string $message): string
    {
        $map = [
            'ą' => 'a', 'Ą' => 'A',
            'ć' => 'c', 'Ć' => 'C',
            'ę' => 'e', 'Ę' => 'E',
            'ł' => 'l', 'Ł' => 'L',
            'ń' => 'n', 'Ń' => 'N',
            'ó' => 'o', 'Ó' => 'O',
            'ś' => 's', 'Ś' => 'S',
            'ź' => 'z', 'Ź' => 'Z',
            'ż' => 'z', 'Ż' => 'Z',
            '—' => '-', '–' => '-', '−' => '-',
            '„' => '"', '”' => '"', '“' => '"',
            '«' => '"', '»' => '"',
            '…' => '...',
            '×' => 'x',
            "\u{00A0}" => ' ',
        ];

        return strtr($message, $map);
    }
}
