<?php

namespace Tests\Feature;

use App\Mail\DebtReminderMail;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\DebtCaseContact;
use App\Models\FormOrder;
use App\Models\User;
use App\Services\IfirmaApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DebtReminderMailTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputBufferLevel = ob_get_level();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_collections_show_includes_reminder_modal(): void
    {
        [$user, $case] = $this->caseWithOrder();

        $response = $this->actingAs($user)->get(route('accounting.collections.show', $case));

        $response->assertOk();
        $response->assertSee('Wyślij przypomnienie', false);
        $response->assertSee('debtReminderModal', false);
        $response->assertSee('Przypomnienie o płatności', false);
        $response->assertSee('Ponaglenie (formalniejsze)', false);
        $response->assertSee('Załącz PDF faktury z iFirma', false);
        $response->assertSee('debt-reminder-recipient-checkbox', false);
        $response->assertSee('Zamawiający', false);
        $response->assertSee('dluznik@example.test', false);
        $response->assertDontSee('debtReminderRecipientKey', false);
    }

    public function test_user_can_send_test_reminder_email(): void
    {
        Mail::fake();
        [$user, $case] = $this->caseWithOrder();

        $response = $this->actingAs($user)->post(route('accounting.collections.send-reminder', $case), [
            'template' => 'reminder',
            'subject' => 'Test temat',
            'body' => "Dzień dobry,\n\ntest",
            'send_target' => 'test',
            'test_email' => 'tester@example.test',
            'recipient_email' => 'dluznik@example.test',
        ]);

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');

        Mail::assertSent(DebtReminderMail::class, function (DebtReminderMail $mail) {
            return $mail->hasTo('tester@example.test')
                && $mail->subjectLine === 'Test temat';
        });

        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_EMAIL,
            'outcome' => 'test_sent',
        ]);
        $this->assertSame(DebtCase::STATUS_OPEN, $case->fresh()->status);
    }

    public function test_real_send_logs_email_and_moves_open_case_to_in_progress(): void
    {
        Mail::fake();
        [$user, $case] = $this->caseWithOrder();

        DebtCaseContact::create([
            'debt_case_id' => $case->id,
            'created_by' => $user->id,
            'contact_type' => DebtCaseContact::TYPE_EMAIL,
            'value' => 'sekretariat@szkola.test',
            'label' => 'Sekretariat',
        ]);

        $response = $this->actingAs($user)->post(route('accounting.collections.send-reminder', $case), [
            'template' => 'dunning',
            'subject' => 'Ponaglenie FV',
            'body' => 'Treść ponaglenia',
            'send_target' => 'recipient',
            'recipient_emails' => ['sekretariat@szkola.test'],
            'test_email' => 'tester@example.test',
        ]);

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');

        Mail::assertSent(DebtReminderMail::class, fn (DebtReminderMail $mail) => $mail->hasTo('sekretariat@szkola.test'));

        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_EMAIL,
            'outcome' => 'sent',
        ]);
        $this->assertSame(DebtCase::STATUS_IN_PROGRESS, $case->fresh()->status);
    }

    public function test_real_send_to_multiple_selected_recipients(): void
    {
        Mail::fake();
        [$user, $case] = $this->caseWithOrder();

        DebtCaseContact::create([
            'debt_case_id' => $case->id,
            'created_by' => $user->id,
            'contact_type' => DebtCaseContact::TYPE_EMAIL,
            'value' => 'sekretariat@szkola.test',
            'label' => 'Sekretariat',
        ]);

        $response = $this->actingAs($user)->post(route('accounting.collections.send-reminder', $case), [
            'template' => 'reminder',
            'subject' => 'Przypomnienie multi',
            'body' => 'Treść do kilku odbiorców',
            'send_target' => 'recipient',
            'recipient_emails' => [
                'dluznik@example.test',
                'sekretariat@szkola.test',
            ],
            'recipient_email' => 'dodatkowy@example.test',
        ]);

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');

        Mail::assertSent(DebtReminderMail::class, function (DebtReminderMail $mail) {
            return $mail->hasTo('dluznik@example.test')
                && $mail->hasTo('sekretariat@szkola.test')
                && $mail->hasTo('dodatkowy@example.test');
        });

        $action = DebtCaseAction::query()
            ->where('debt_case_id', $case->id)
            ->where('action_type', DebtCaseAction::TYPE_EMAIL)
            ->latest('id')
            ->first();
        $this->assertNotNull($action);
        $this->assertStringContainsString('dluznik@example.test', (string) $action->note);
        $this->assertStringContainsString('sekretariat@szkola.test', (string) $action->note);
        $this->assertStringContainsString('dodatkowy@example.test', (string) $action->note);
    }

    public function test_real_send_requires_at_least_one_recipient(): void
    {
        Mail::fake();
        [$user, $case] = $this->caseWithOrder();

        $response = $this->actingAs($user)->post(route('accounting.collections.send-reminder', $case), [
            'template' => 'reminder',
            'subject' => 'Bez odbiorcy',
            'body' => 'Treść',
            'send_target' => 'recipient',
            'recipient_emails' => [],
        ]);

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHasErrors('recipient_emails');
        Mail::assertNothingSent();
    }

    public function test_send_with_ifirma_pdf_attachment_and_upload(): void
    {
        Mail::fake();
        Storage::fake('local');
        [$user, $case] = $this->caseWithOrder(['ifirma_invoice_id' => 12345]);

        $ifirma = Mockery::mock(IfirmaApiService::class);
        $ifirma->shouldReceive('downloadInvoicePdf')
            ->once()
            ->with('12345')
            ->andReturn([
                'status' => 'success',
                'content' => '%PDF-1.4 fake',
            ]);
        $this->app->instance(IfirmaApiService::class, $ifirma);

        $upload = UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->post(route('accounting.collections.send-reminder', $case), [
            'template' => 'reminder',
            'subject' => 'Z załącznikami',
            'body' => 'Treść',
            'send_target' => 'recipient',
            'recipient_emails' => ['dluznik@example.test'],
            'attach_ifirma_pdf' => '1',
            'attachment' => $upload,
        ]);

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');

        Mail::assertSent(DebtReminderMail::class, function (DebtReminderMail $mail) {
            return count($mail->fileAttachments) === 2;
        });
    }

    /**
     * @param  array<string, mixed>  $orderAttrs
     * @return array{0: User, 1: DebtCase}
     */
    private function caseWithOrder(array $orderAttrs = []): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
            'email' => 'operator@example.test',
        ]);

        $order = FormOrder::create(array_merge([
            'product_name' => 'Szkolenie windykacja mail',
            'product_price' => 365,
            'order_date' => now()->subDays(40),
            'invoice_number' => '200/8/2026',
            'orderer_name' => 'Jan Kowalski',
            'orderer_email' => 'dluznik@example.test',
            'invoice_payment_delay' => 14,
        ], $orderAttrs));

        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => $order->invoice_number,
            'amount_gross' => 365,
            'due_date' => now()->subDays(10),
            'opened_at' => now(),
        ]);

        return [$user, $case];
    }
}
