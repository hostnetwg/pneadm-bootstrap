<?php

namespace Tests\Feature;

use App\Models\DebtCase;
use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DebtCaseInvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputBufferLevel = ob_get_level();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_user_can_upload_preview_and_delete_invoice_pdf(): void
    {
        [$user, $case] = $this->caseWithOrder();

        $showBefore = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $showBefore->assertOk();
        $showBefore->assertSee('PDF faktury', false);
        $showBefore->assertSee('Wgraj PDF', false);

        $upload = UploadedFile::fake()->create('faktura-26.pdf', 120, 'application/pdf');
        $response = $this->actingAs($user)->post(route('accounting.collections.invoice-pdf.upload', $case), [
            'invoice_pdf' => $upload,
        ]);

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');

        $case->refresh();
        $this->assertTrue($case->hasInvoicePdf());
        $this->assertSame('faktura-26.pdf', $case->invoice_pdf_original_name);
        Storage::disk('local')->assertExists($case->invoice_pdf_path);

        $showAfter = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $showAfter->assertOk();
        $showAfter->assertSee('Podgląd PDF', false);
        $showAfter->assertSee('Załącz PDF faktury ze sprawy', false);
        $html = $showAfter->getContent();
        $this->assertMatchesRegularExpression(
            '/id="debtReminderAttachCasePdf"[^>]*\bchecked\b|\bchecked\b[^>]*id="debtReminderAttachCasePdf"/is',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="debtReminderAttachIfirma"[^>]*\bchecked\b|\bchecked\b[^>]*id="debtReminderAttachIfirma"/is',
            $html
        );

        $preview = $this->actingAs($user)->get(route('accounting.collections.invoice-pdf.preview', $case));
        $preview->assertOk();
        $preview->assertHeader('content-type', 'application/pdf');

        $delete = $this->actingAs($user)->delete(route('accounting.collections.invoice-pdf.destroy', $case));
        $delete->assertRedirect(route('accounting.collections.show', $case));
        $this->assertFalse($case->fresh()->hasInvoicePdf());
    }

    public function test_closing_debt_case_deletes_invoice_pdf_from_storage(): void
    {
        [$user, $case] = $this->caseWithOrder();

        $this->actingAs($user)->post(route('accounting.collections.invoice-pdf.upload', $case), [
            'invoice_pdf' => UploadedFile::fake()->create('faktura-26.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $case->refresh();
        $path = $case->invoice_pdf_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $close = $this->actingAs($user)->post(route('accounting.collections.actions.store', $case), [
            'action_type' => \App\Models\DebtCaseAction::TYPE_CLOSE,
            'note' => 'Opłacone — zamykam',
        ]);
        $close->assertRedirect(route('accounting.collections.show', $case));

        $case->refresh();
        $this->assertSame(DebtCase::STATUS_CLOSED, $case->status);
        $this->assertNull($case->invoice_pdf_path);
        $this->assertNull($case->invoice_pdf_original_name);
        $this->assertFalse($case->hasInvoicePdf());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_auto_close_also_deletes_invoice_pdf(): void
    {
        [$user, $case] = $this->caseWithOrder();

        $this->actingAs($user)->post(route('accounting.collections.invoice-pdf.upload', $case), [
            'invoice_pdf' => UploadedFile::fake()->create('faktura-auto.pdf', 80, 'application/pdf'),
        ])->assertRedirect();

        $case->refresh();
        $path = $case->invoice_pdf_path;
        Storage::disk('local')->assertExists($path);

        $closed = app(\App\Services\DebtCaseAutoCloseService::class)->closeIfFullyPaid(
            $case,
            $user,
            \App\Services\IfirmaInvoicePaymentStatusService::STATUS_PAID
        );

        $this->assertTrue($closed);
        $case->refresh();
        $this->assertSame(DebtCase::STATUS_CLOSED, $case->status);
        $this->assertNull($case->invoice_pdf_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_collections_index_shows_pdf_icon_when_invoice_pdf_attached(): void
    {
        [$user, $case] = $this->caseWithOrder();

        $withoutPdf = $this->actingAs($user)->get(route('accounting.collections.index'));
        $withoutPdf->assertOk();
        $withoutPdf->assertSee('FV: 26/8/2026', false);
        $withoutPdf->assertDontSee('bi-file-earmark-pdf-fill', false);

        $this->actingAs($user)->post(route('accounting.collections.invoice-pdf.upload', $case), [
            'invoice_pdf' => UploadedFile::fake()->create('faktura-26.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $withPdf = $this->actingAs($user)->get(route('accounting.collections.index'));
        $withPdf->assertOk();
        $withPdf->assertSee('bi-file-earmark-pdf-fill', false);
        $withPdf->assertSee(route('accounting.collections.invoice-pdf.preview', $case), false);
    }

    /**
     * @return array{0: User, 1: DebtCase}
     */
    private function caseWithOrder(): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie PDF',
            'product_price' => 365,
            'order_date' => now()->subDays(20),
            'invoice_number' => '26/8/2026',
            'orderer_email' => 'dluznik@example.test',
        ]);

        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '26/8/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);

        return [$user, $case];
    }
}
