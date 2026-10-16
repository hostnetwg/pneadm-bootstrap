<?php

namespace Tests\Feature;

use App\Mail\DebtReminderMail;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\User;
use App\Services\DebtReminderTemplateService;
use App\Services\IfirmaApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class DebtReminderSmsTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputBufferLevel = ob_get_level();

        config([
            'services.smsapi.enabled' => true,
            'services.smsapi.token' => 'test-token',
            'services.smsapi.from' => '',
            'services.smsapi.test_phones' => ['+48 501 654 274', '+48 510 396 579'],
            'services.smsapi.test_phone_emails' => [
                '48501654274' => 'waldemar.grabowski@hostnet.pl',
                '48510396579' => 'luman0599@gmail.com',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: DebtCase}
     */
    private function caseWithOrder(array $orderOverrides = []): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create(array_merge([
            'product_name' => 'Szkolenie SMS',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '100/9/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'orderer_name' => 'Jan Kowalski',
            'orderer_email' => 'jan@example.test',
            'orderer_phone' => '501111222',
        ], $orderOverrides));

        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'amount_gross' => 365,
            'invoice_number' => '100/9/2026',
            'due_date' => now()->subDays(2)->toDateString(),
            'assigned_to_id' => $user->id,
            'opened_at' => now(),
        ]);

        return [$user, $case];
    }

    public function test_collections_show_includes_sms_modal_and_orderer_phone(): void
    {
        [$user, $case] = $this->caseWithOrder();

        $response = $this->actingAs($user)->get(route('accounting.collections.show', $case));

        $response->assertOk();
        $response->assertSee('debtReminderSmsModal', false);
        $response->assertSee('Wyślij SMS', false);
        $response->assertSee('+48 501 111 222', false);
        $response->assertSee('0 SMS-ów', false);
        $response->assertDontSee('Dodaj nazwę produktu po numerze FV', false);
        $response->assertDontSee('debtReminderSmsIncludeTraining', false);
        $response->assertSee('Dodaj nr konta bankowego', false);
        $response->assertSee('debtReminderSmsIncludeBank', false);
        $response->assertSee('Wyślij też e-mail przypomnienia tuż przed SMS', false);
        $response->assertSee('debtReminderSmsAlsoSendEmail', false);
        $response->assertSee('za szkolenie: Szkolenie SMS', false);
    }

    public function test_sms_reminder_includes_short_training_title(): void
    {
        [, $case] = $this->caseWithOrder([
            'product_name' => 'Bardzo długi tytuł szkolenia z zarządzania projektami edukacyjnymi i coachingiem',
        ]);

        $templates = app(DebtReminderTemplateService::class);
        $this->assertNull($templates->smsTrainingSnippet($case, 40));
        $this->assertSame('FV 100/9/2026', $templates->smsInvoiceMarker($case));

        $payload = $templates->buildSms($case, DebtReminderTemplateService::TEMPLATE_REMINDER);
        $this->assertStringContainsString('za szkolenie: Bardzo długi...', $payload['body']);
        $this->assertStringContainsString('Dotyczy FV 100/9/2026.', $payload['body']);
        $this->assertStringContainsString('Platforma Nowoczesnej Edukacji:', $payload['body']);
        $this->assertStringContainsString('Prosimy o zapłatę na rachunek z faktury.', $payload['body']);
        $this->assertStringNotContainsString('SZKOLENIE:', $payload['body']);
        $this->assertStringNotContainsString('Konto:', $payload['body']);
        $this->assertNull($payload['training_snippet']);
    }

    public function test_sms_bank_snippet_from_payment_display_options(): void
    {
        \App\Models\PaymentDisplayOption::query()->delete();
        \App\Models\PaymentDisplayOption::create([
            'show_pay_publigo' => true,
            'show_pay_online' => true,
            'show_deferred_order' => true,
            'show_order_form' => true,
            'show_order_form_v2' => false,
            'default_signup_order_form_variant' => 'legacy',
            'show_order_form_alt' => true,
            'order_form_auto_fill_test_data' => false,
            'company_bank_account' => 'PL61109010140000071219812874',
            'default_post_end_access_duration_value' => 2,
            'default_post_end_access_duration_unit' => 'months',
        ]);
        \App\Models\PaymentDisplayOption::forgetSettingsCache();

        [, $case] = $this->caseWithOrder();
        $templates = app(DebtReminderTemplateService::class);

        $this->assertSame(
            ' Konto:PL61109010140000071219812874',
            $templates->smsBankSnippet()
        );

        $payload = $templates->buildSms($case, DebtReminderTemplateService::TEMPLATE_REMINDER);
        $this->assertSame(' Konto:PL61109010140000071219812874', $payload['bank_snippet']);
        $this->assertStringNotContainsString('Konto:', $payload['body']);
    }

    public function test_real_sms_send_logs_action_and_increments_counter(): void
    {
        Http::fake([
            'api.smsapi.pl/*' => Http::response([
                'count' => 1,
                'list' => [
                    ['id' => 'abc123', 'points' => 0.16, 'number' => '48501111222', 'status' => 'QUEUE'],
                ],
            ], 200),
        ]);

        [$user, $case] = $this->caseWithOrder();

        $response = $this->actingAs($user)->post(route('accounting.collections.send-reminder-sms', $case), [
            'template' => 'reminder',
            'body' => 'PNEDU: test SMS przypomnienia FV 100/9/2026',
            'send_target' => 'recipient',
            'test_phone' => '48501654274',
        ]);

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_SMS,
            'outcome' => 'sent',
            'channel' => 'sms',
        ]);
        $fresh = $case->fresh();
        $this->assertSame(1, $fresh->reminderSmsSentCount());
        $this->assertTrue($fresh->reminderSmsSentToday());
        $this->assertNotNull($fresh->lastReminderSmsSentAt());
        $this->assertSame(DebtCase::STATUS_IN_PROGRESS, $fresh->status);

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('1 SMS', false);
        $show->assertSee('Ostatnio: dziś', false);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sms.do')
                && $request['to'] === '48501111222'
                && str_contains((string) $request['message'], 'FV 100/9/2026');
        });
    }

    public function test_test_sms_does_not_increase_counter(): void
    {
        Http::fake([
            'api.smsapi.pl/*' => Http::response([
                'count' => 1,
                'list' => [
                    ['id' => 'test1', 'points' => 0.16, 'number' => '48501654274', 'status' => 'QUEUE'],
                ],
            ], 200),
        ]);

        [$user, $case] = $this->caseWithOrder();

        $this->actingAs($user)->post(route('accounting.collections.send-reminder-sms', $case), [
            'template' => 'reminder',
            'body' => 'SMS testowy',
            'send_target' => 'test',
            'test_phone' => '48501654274',
        ])->assertRedirect(route('accounting.collections.show', $case));

        $this->assertSame(0, $case->fresh()->reminderSmsSentCount());
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_SMS,
            'outcome' => 'test_sent',
        ]);
        $this->assertSame(DebtCase::STATUS_OPEN, $case->fresh()->status);
    }

    public function test_real_sms_requires_orderer_phone(): void
    {
        Http::fake();
        [$user, $case] = $this->caseWithOrder(['orderer_phone' => null]);

        $response = $this->actingAs($user)->from(route('accounting.collections.show', $case))->post(
            route('accounting.collections.send-reminder-sms', $case),
            [
                'template' => 'reminder',
                'body' => 'SMS',
                'send_target' => 'recipient',
            ]
        );

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHasErrors('body');
        Http::assertNothingSent();
    }

    public function test_also_send_email_sends_reminder_before_real_sms(): void
    {
        Mail::fake();
        Http::fake([
            'api.smsapi.pl/*' => Http::response([
                'count' => 1,
                'list' => [['id' => 'sms1', 'points' => 0.16, 'number' => '48501111222', 'status' => 'QUEUE']],
            ], 200),
        ]);

        $ifirma = Mockery::mock(IfirmaApiService::class);
        $ifirma->shouldReceive('downloadInvoicePdf')
            ->once()
            ->with('1001')
            ->andReturn(['status' => 'success', 'content' => '%PDF-1.4 fake']);
        $this->app->instance(IfirmaApiService::class, $ifirma);

        [$user, $case] = $this->caseWithOrder([
            'ifirma_invoice_id' => 1001,
            'orderer_email' => 'zamawiajacy@example.test',
        ]);
        FormOrderParticipant::create([
            'form_order_id' => $case->form_order_id,
            'participant_firstname' => 'Ala',
            'participant_lastname' => 'Nowak',
            'participant_email' => 'uczestnik@example.test',
            'is_primary' => true,
        ]);

        $this->actingAs($user)->post(route('accounting.collections.send-reminder-sms', $case), [
            'template' => 'reminder',
            'body' => 'SMS z e-mailem',
            'send_target' => 'recipient',
            'also_send_email' => '1',
        ])->assertRedirect(route('accounting.collections.show', $case))
            ->assertSessionHas('success');

        Mail::assertSent(DebtReminderMail::class, function (DebtReminderMail $mail) {
            return $mail->hasTo('zamawiajacy@example.test')
                && $mail->hasTo('uczestnik@example.test')
                && str_contains($mail->plainBody, 'Dla wygody')
                && count($mail->fileAttachments) === 1;
        });

        $this->assertSame(1, $case->fresh()->reminderEmailsSentCount());
        $this->assertSame(1, $case->fresh()->reminderSmsSentCount());
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_EMAIL,
            'outcome' => 'sent',
        ]);
    }

    public function test_also_send_email_on_test_sms_maps_phone_to_test_email(): void
    {
        Mail::fake();
        Http::fake([
            'api.smsapi.pl/*' => Http::response([
                'count' => 1,
                'list' => [['id' => 't1', 'points' => 0.16, 'number' => '48501654274', 'status' => 'QUEUE']],
            ], 200),
        ]);

        $ifirma = Mockery::mock(IfirmaApiService::class);
        $ifirma->shouldReceive('downloadInvoicePdf')
            ->once()
            ->andReturn(['status' => 'success', 'content' => '%PDF-1.4 fake']);
        $this->app->instance(IfirmaApiService::class, $ifirma);

        [$user, $case] = $this->caseWithOrder(['ifirma_invoice_id' => 55]);

        $this->actingAs($user)->post(route('accounting.collections.send-reminder-sms', $case), [
            'template' => 'reminder',
            'body' => 'SMS test',
            'send_target' => 'test',
            'test_phone' => '48501654274',
            'also_send_email' => '1',
        ])->assertRedirect(route('accounting.collections.show', $case));

        Mail::assertSent(DebtReminderMail::class, fn (DebtReminderMail $mail) => $mail->hasTo('waldemar.grabowski@hostnet.pl'));
        $this->assertSame(0, $case->fresh()->reminderEmailsSentCount());
        $this->assertSame(0, $case->fresh()->reminderSmsSentCount());
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_EMAIL,
            'outcome' => 'test_sent',
        ]);
    }

    public function test_also_send_email_failure_still_sends_sms(): void
    {
        Mail::fake();
        Http::fake([
            'api.smsapi.pl/*' => Http::response([
                'count' => 1,
                'list' => [['id' => 'sms2', 'points' => 0.16, 'number' => '48501111222', 'status' => 'QUEUE']],
            ], 200),
        ]);

        $ifirma = Mockery::mock(IfirmaApiService::class);
        $ifirma->shouldReceive('downloadInvoicePdf')
            ->once()
            ->andReturn(['status' => 'error', 'message' => 'brak PDF']);
        $this->app->instance(IfirmaApiService::class, $ifirma);

        [$user, $case] = $this->caseWithOrder(['ifirma_invoice_id' => 77]);

        $this->actingAs($user)->post(route('accounting.collections.send-reminder-sms', $case), [
            'template' => 'reminder',
            'body' => 'SMS mimo błędu maila',
            'send_target' => 'recipient',
            'also_send_email' => '1',
        ])->assertRedirect(route('accounting.collections.show', $case))
            ->assertSessionHas('success')
            ->assertSessionHas('warning');

        Mail::assertNothingSent();
        $this->assertSame(0, $case->fresh()->reminderEmailsSentCount());
        $this->assertSame(1, $case->fresh()->reminderSmsSentCount());
    }
}
