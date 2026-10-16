<?php

namespace Tests\Unit;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\Course;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\DebtCollectionSetting;
use App\Models\FormOrder;
use App\Models\Instructor;
use App\Models\User;
use App\Services\DebtReminderTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtReminderTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_reminder_and_dunning_templates_with_case_data(): void
    {
        $instructor = Instructor::create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna.nowak@example.test',
            'is_active' => true,
        ]);
        $start = now()->setTimezone(config('app.timezone'))->setTime(9, 30);
        $course = Course::create([
            'title' => 'AI w edukacji',
            'description' => 'Test',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(4),
            'instructor_id' => $instructor->id,
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);

        $order = FormOrder::create([
            'product_id' => $course->id,
            'product_name' => 'AI w edukacji',
            'product_price' => 499.5,
            'order_date' => now()->subDays(30),
            'invoice_number' => '88/8/2026',
            'orderer_email' => 'a@example.test',
        ]);
        $order->setRelation('course', $course->setRelation('instructor', $instructor));

        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '88/8/2026',
            'amount_gross' => 499.5,
            'due_date' => now()->subDays(5)->toDateString(),
            'opened_at' => now(),
        ]);
        $case->setRelation('formOrder', $order);

        config(['services.ifirma.bank_account' => '12 3456 7890 1234 5678 9012 3456']);
        DebtCollectionSetting::query()->updateOrCreate(
            ['id' => DebtCollectionSetting::SINGLETON_ID],
            ['contact_phone' => '+48 600 100 200']
        );
        DebtCollectionSetting::forgetSettingsCache();

        $service = app(DebtReminderTemplateService::class);
        $reminder = $service->build($case, DebtReminderTemplateService::TEMPLATE_REMINDER);
        $dunning = $service->build($case, DebtReminderTemplateService::TEMPLATE_DUNNING);

        $this->assertSame(
            'Przypomnienie o płatności za fakturę 88/8/2026 — Platforma Nowoczesnej Edukacji',
            $reminder['subject']
        );
        $this->assertStringContainsString('nie odnotowaliśmy dotychczas wpłaty za szkolenie „AI w edukacji”', $reminder['body']);
        $this->assertStringContainsString('Temat: AI w edukacji', $reminder['body']);
        $this->assertStringContainsString('Prowadzący: Anna Nowak', $reminder['body']);
        $this->assertStringContainsString('Koszt: 499,50 zł (termin płatności:', $reminder['body']);
        $this->assertStringContainsString('po terminie', $reminder['body']);
        $this->assertStringContainsString('Tytuł przelewu: 88/8/2026', $reminder['body']);
        $this->assertStringContainsString('Nr konta: PL 12 3456 7890 1234 5678 9012 3456', $reminder['body']);
        $this->assertStringContainsString('Prosimy o uregulowanie należności na rachunek wskazany na fakturze.', $reminder['body']);
        $this->assertStringNotContainsString('Fakturę przesłaliśmy wcześniej e-mailem.', $reminder['body']);
        $this->assertStringNotContainsString('Dla wygody', $reminder['body']);
        $this->assertStringNotContainsString('Pozostało do zapłaty:', $reminder['body']);
        $this->assertStringContainsString("Pozdrawiam\nWaldemar Grabowski", $reminder['body']);
        $this->assertStringContainsString('tel. +48 600 100 200', $reminder['body']);
        $this->assertSame(
            DebtReminderTemplateService::INVOICE_ATTACHMENT_SENTENCE_REMINDER,
            $reminder['invoice_attachment_sentence']
        );

        $this->assertStringContainsString('Ponaglenie', $dunning['subject']);
        $this->assertStringContainsString('SZKOLENIE: AI w edukacji', $dunning['subject']);
        $this->assertStringContainsString('nieuregulowana', $dunning['body']);
        $this->assertStringContainsString('Prowadzący: Anna Nowak', $dunning['body']);
        $this->assertStringContainsString('tel. +48 600 100 200', $dunning['body']);
        $this->assertStringNotContainsString('W załączeniu przesyłamy fakturę', $dunning['body']);
        $this->assertTrue($service->canAttachIfirmaPdf($case));
        $this->assertSame('88_8_2026', $service->ifirmaPdfLookupKey($case));
        $this->assertFalse($service->canIncludeOrderConfirmationLink($case));
    }

    public function test_reminder_uses_remaining_balance_and_prior_invoice_email(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $order = FormOrder::create([
            'product_name' => 'Szkolenie saldo',
            'product_price' => 365,
            'order_date' => now()->subDays(20),
            'invoice_number' => '649/9/2026',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_IN_PROGRESS,
            'invoice_number' => '649/9/2026',
            'amount_gross' => 365,
            'due_date' => now()->subDays(3)->toDateString(),
            'opened_at' => now(),
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'test.csv',
            'stored_path' => 'bank/test.csv',
            'file_hash' => str_repeat('a', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        $tx = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->toDateString(),
            'amount' => 100,
            'currency' => 'PLN',
            'description' => 'WPŁATA CZĘŚCIOWA',
            'account_label' => 'Test',
            'fingerprint' => str_repeat('b', 64),
            'is_incoming' => true,
        ]);
        BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'debt_case_id' => $case->id,
            'form_order_id' => $order->id,
            'status' => BankTransactionMatch::STATUS_ACCEPTED,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['manual_case_link'],
            'allocated_amount' => 100,
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);

        DebtCaseAction::create([
            'debt_case_id' => $case->id,
            'user_id' => $user->id,
            'action_type' => DebtCaseAction::TYPE_EMAIL,
            'channel' => 'email',
            'outcome' => 'sent',
            'happened_at' => now()->subDay(),
            'note' => '[WYSYŁKA] · Do: a@example.test · Temat: FV · Załączniki: PDF iFirma (FV.pdf)',
        ]);

        DebtCollectionSetting::query()->updateOrCreate(
            ['id' => DebtCollectionSetting::SINGLETON_ID],
            ['contact_phone' => '501 654 274']
        );
        DebtCollectionSetting::forgetSettingsCache();

        $service = app(DebtReminderTemplateService::class);
        $this->assertTrue($service->hasConfirmedInvoiceEmailSent($case));

        $reminder = $service->build($case->fresh(['formOrder', 'bankTransactionMatches.transaction', 'actions']), DebtReminderTemplateService::TEMPLATE_REMINDER);
        $this->assertStringContainsString('Koszt: 265,00 zł (termin płatności:', $reminder['body']);
        $this->assertStringContainsString('Tytuł przelewu: 649/9/2026', $reminder['body']);
        $this->assertStringContainsString('Fakturę przesłaliśmy wcześniej e-mailem.', $reminder['body']);
        $this->assertSame(
            DebtReminderTemplateService::INVOICE_ATTACHMENT_SENTENCE_REMINDER_AGAIN,
            $reminder['invoice_attachment_sentence']
        );

        $sms = $service->buildSms($case->fresh(['formOrder', 'bankTransactionMatches.transaction', 'actions']), DebtReminderTemplateService::TEMPLATE_REMINDER);
        $this->assertStringContainsString('265,00 zł', $sms['body']);
        $this->assertStringContainsString('za szkolenie: Szkolenie saldo', $sms['body']);
        $this->assertStringContainsString('Fakturę FV 649/9/2026 wysłaliśmy e-mailem.', $sms['body']);
        $this->assertStringContainsString('Kontakt: 501 654 274.', $sms['body']);
        $this->assertStringStartsWith('Platforma Nowoczesnej Edukacji:', $sms['body']);
    }

    public function test_sms_short_training_title_takes_two_words_and_preposition_extension(): void
    {
        $service = app(DebtReminderTemplateService::class);

        $this->assertSame(
            'Konkurs na dyrektora...',
            $service->smsShortTrainingTitle('Konkurs na dyrektora szkoły podstawowej w Warszawie')
        );
        $this->assertSame(
            'AI w edukacji',
            $service->smsShortTrainingTitle('AI w edukacji')
        );
        $this->assertSame(
            'Działalność i protokołowanie...',
            $service->smsShortTrainingTitle('Działalność i protokołowanie rad pedagogicznych.')
        );
    }

    public function test_to_gsm_friendly_sms_strips_polish_and_lowers_part_count(): void
    {
        $service = app(DebtReminderTemplateService::class);
        $withDiacritics = 'Platforma Nowoczesnej Edukacji: nie odnotowaliśmy wpłaty 365,00 zł za szkolenie: '
            .'Konkurs na dyrektora... Fakturę FV 649/9/2026 wysłaliśmy e-mailem. '
            .'Prosimy o zapłatę na rachunek z faktury. Kontakt: 501 654 274.';
        $friendly = $service->toGsmFriendlySms($withDiacritics);

        $this->assertStringContainsString('nie odnotowalismy wplaty', $friendly);
        $this->assertStringContainsString('Fakture FV 649/9/2026 wyslalismy e-mailem.', $friendly);
        $this->assertStringNotContainsString('ą', $friendly);
        $this->assertStringNotContainsString('ł', $friendly);
        $this->assertGreaterThan(
            $service->estimateSmsParts($friendly),
            $service->estimateSmsParts($withDiacritics)
        );
        $this->assertSame(2, $service->estimateSmsParts($friendly));
        $this->assertGreaterThanOrEqual(3, $service->estimateSmsParts($withDiacritics));
    }

    public function test_reminder_without_training_title_uses_generic_sentence(): void
    {
        $order = FormOrder::create([
            'product_name' => '',
            'product_price' => 100,
            'order_date' => now(),
            'invoice_number' => '1/1/2026',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '1/1/2026',
            'amount_gross' => 100,
            'opened_at' => now(),
        ]);
        $case->setRelation('formOrder', $order);

        $service = app(DebtReminderTemplateService::class);
        $reminder = $service->build($case, DebtReminderTemplateService::TEMPLATE_REMINDER);

        $this->assertStringContainsString(
            'nie odnotowaliśmy dotychczas wpłaty za szkolenie, którego dotyczy faktura nr 1/1/2026.',
            $reminder['body']
        );
        $this->assertStringNotContainsString('„', $reminder['body']);
    }

    public function test_order_confirmation_link_helpers_and_body_sync(): void
    {
        config(['services.pnedu_frontend_url' => 'https://pnedu.pl']);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'product_price' => 100,
            'order_date' => now(),
            'invoice_number' => '1/1/2026',
            'ident' => '260808-ABC123',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '1/1/2026',
            'amount_gross' => 100,
            'opened_at' => now(),
        ]);
        $case->setRelation('formOrder', $order);

        $service = app(DebtReminderTemplateService::class);
        $this->assertTrue($service->canIncludeOrderConfirmationLink($case));
        $url = 'https://pnedu.pl/orders/260808-ABC123/pdf';
        $this->assertSame($url, $service->orderConfirmationPdfUrl($case));

        $block = $service->orderConfirmationBodyBlock($case);
        $this->assertNotNull($block);
        $this->assertStringContainsString('zamówienia #'.$order->id, $block);
        $this->assertStringContainsString($url, $block);

        $withLink = $service->syncOrderConfirmationLinkInBody("Dzień dobry,\n\ntest", $case, true);
        $this->assertStringContainsString($url, $withLink);
        $this->assertSame(1, substr_count($withLink, $url));

        $again = $service->syncOrderConfirmationLinkInBody($withLink, $case, true);
        $this->assertSame(1, substr_count($again, $url));

        $without = $service->syncOrderConfirmationLinkInBody($withLink, $case, false);
        $this->assertStringNotContainsString($url, $without);
        $this->assertStringContainsString('Dzień dobry', $without);

        $withSignature = $service->syncOrderConfirmationLinkInBody(
            "Treść główna.\n\nZ poważaniem,\nZespół Platformy Nowoczesnej Edukacji\nkontakt@pnedu.pl",
            $case,
            true
        );
        $this->assertMatchesRegularExpression(
            '/Treść główna\.\n\nFaktura została wystawiona na podstawie zamówienia #'.$order->id
            .', pobierz potwierdzenie zamówienia: '.preg_quote($url, '/')
            ."\n\nZ poważaniem,/u",
            $withSignature
        );
    }

    public function test_order_confirmation_link_skipped_without_ident(): void
    {
        config(['services.pnedu_frontend_url' => 'https://pnedu.pl']);

        $order = FormOrder::create([
            'product_name' => 'Bez ident',
            'product_price' => 100,
            'order_date' => now(),
            'ident' => null,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);
        $case->setRelation('formOrder', $order);

        $service = app(DebtReminderTemplateService::class);
        $this->assertFalse($service->canIncludeOrderConfirmationLink($case));
        $body = 'Treść bez zmian';
        $this->assertSame($body, $service->syncOrderConfirmationLinkInBody($body, $case, true));
    }

    public function test_dunning_invoice_attachment_sentence_sync(): void
    {
        $service = app(DebtReminderTemplateService::class);
        $base = "Prosimy o płatność.\n\nZ poważaniem,\nZespół";

        $with = $service->syncInvoiceAttachmentSentenceInBody($base, true);
        $this->assertStringContainsString(DebtReminderTemplateService::INVOICE_ATTACHMENT_SENTENCE, $with);
        $this->assertMatchesRegularExpression(
            '/Prosimy o płatność\.\n\nW załączeniu przesyłamy fakturę\.\n\nZ poważaniem,/u',
            $with
        );
        $this->assertStringNotContainsString('jeśli została dołączona', $with);

        $legacy = "Prosimy o płatność.\nW załączeniu przesyłamy fakturę (jeśli została dołączona).\n\nZ poważaniem,\nZespół";
        $stripped = $service->syncInvoiceAttachmentSentenceInBody($legacy, false);
        $this->assertStringNotContainsString('W załączeniu przesyłamy fakturę', $stripped);
        $this->assertStringContainsString('Z poważaniem,', $stripped);
    }

    public function test_reminder_invoice_attachment_sentence_inserts_before_payment_request(): void
    {
        $service = app(DebtReminderTemplateService::class);
        $base = "Fakturę przesłaliśmy wcześniej e-mailem. Prosimy o uregulowanie należności na rachunek wskazany na fakturze.\n\nPozdrawiam\nWaldemar Grabowski";

        $with = $service->syncInvoiceAttachmentSentenceInBody(
            $base,
            true,
            DebtReminderTemplateService::INVOICE_ATTACHMENT_SENTENCE_REMINDER_AGAIN
        );

        $this->assertStringContainsString(
            'Fakturę przesłaliśmy wcześniej e-mailem. Dla wygody ponownie załączamy ją do tej wiadomości w pliku PDF. Prosimy o uregulowanie należności na rachunek wskazany na fakturze.',
            $with
        );
        $this->assertStringContainsString("Pozdrawiam\nWaldemar Grabowski", $with);

        $stripped = $service->syncInvoiceAttachmentSentenceInBody($with, false);
        $this->assertStringNotContainsString('Dla wygody ponownie', $stripped);
        $this->assertStringContainsString('Prosimy o uregulowanie należności na rachunek wskazany na fakturze.', $stripped);
    }
}
