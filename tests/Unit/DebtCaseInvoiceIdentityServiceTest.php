<?php

namespace Tests\Unit;

use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\FormOrder;
use App\Models\User;
use App\Services\DebtCaseInvoiceIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtCaseInvoiceIdentityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_invoice_does_not_open_a_drift_modal(): void
    {
        [$case] = $this->pair('10/8/2026', '10/8/2026', 'KSEF-1', 'KSEF-1', '100', '100');

        $compare = app(DebtCaseInvoiceIdentityService::class)->compare($case);

        $this->assertFalse($compare['show_modal']);
        $this->assertFalse($compare['can_update']);
    }

    public function test_missing_case_invoice_is_not_a_drift_when_the_order_has_one(): void
    {
        [$case] = $this->pair(null, '10/8/2026', null, 'KSEF-1', null, '100');

        $compare = app(DebtCaseInvoiceIdentityService::class)->compare($case);

        $this->assertFalse($compare['show_modal']);
        $this->assertFalse($compare['can_update']);

        $presentation = app(DebtCaseInvoiceIdentityService::class)->presentation($case);
        $this->assertSame('10/8/2026', $presentation['invoice_number']);
        $this->assertSame('KSEF-1', $presentation['ksef_number']);
        $this->assertSame('100', $presentation['ifirma_invoice_id']);
    }

    public function test_whitespace_does_not_count_as_a_different_invoice(): void
    {
        [$case] = $this->pair('10/8/2026', ' 10/8/2026 ', null, null, null, null);

        $compare = app(DebtCaseInvoiceIdentityService::class)->compare($case);

        $this->assertFalse($compare['show_modal']);
    }

    public function test_new_invoice_without_ksef_can_replace_the_old_document(): void
    {
        [$case] = $this->pair(
            '675/8/2026',
            '76/2026',
            'KSEF-OLD',
            null,
            '111',
            '222',
            'Korekta faktury 76/2026 dla 675/8/2026',
        );

        $compare = app(DebtCaseInvoiceIdentityService::class)->compare($case);

        $this->assertTrue($compare['show_modal']);
        $this->assertTrue($compare['can_update']);
        $this->assertTrue($compare['invoice_replaced']);
        $this->assertTrue($compare['clears_ksef']);
        $this->assertSame('Korekta faktury 76/2026 dla 675/8/2026', $compare['notes']);

        $presentation = app(DebtCaseInvoiceIdentityService::class)->presentation($case);
        $this->assertSame('675/8/2026', $presentation['invoice_number']);
        $this->assertSame('KSEF-OLD', $presentation['ksef_number']);
        $this->assertSame('111', $presentation['ifirma_invoice_id']);
    }

    public function test_align_copies_only_the_current_order_invoice_and_clears_stale_ksef(): void
    {
        $user = User::factory()->create();
        [$case, $order] = $this->pair(
            '675/8/2026',
            '76/2026',
            'KSEF-OLD',
            null,
            '111',
            '222',
        );
        $order->product_price = 480;
        $order->invoice_issue_date = '2026-10-01';
        $order->invoice_due_date = '2026-10-15';
        $order->save();

        $result = app(DebtCaseInvoiceIdentityService::class)->alignCaseToOrder($case, $user);

        $this->assertTrue($result['success']);
        $case->refresh();
        $order->refresh();
        $this->assertSame('76/2026', $case->invoice_number);
        $this->assertNull($case->ksef_number);
        $this->assertSame('222', $case->ifirma_invoice_id);
        $this->assertSame('480.00', $case->amount_gross);
        $this->assertSame('2026-10-01', $case->invoice_date?->toDateString());
        $this->assertSame('2026-10-15', $case->due_date?->toDateString());
        $this->assertSame('222', $order->ifirma_invoice_id);
        $this->assertSame('76/2026', $order->invoice_number);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_INVOICE_IDENTITY,
            'user_id' => $user->id,
        ]);
    }

    public function test_cleared_order_invoice_does_not_wipe_the_case(): void
    {
        [$case, $order] = $this->pair('675/8/2026', null, 'KSEF-OLD', null, '111', null);

        $compare = app(DebtCaseInvoiceIdentityService::class)->compare($case);
        $this->assertTrue($compare['show_modal']);
        $this->assertTrue($compare['order_invoice_cleared']);
        $this->assertFalse($compare['can_update']);

        $result = app(DebtCaseInvoiceIdentityService::class)->alignCaseToOrder($case);

        $this->assertFalse($result['success']);
        $case->refresh();
        $order->refresh();
        $this->assertSame('675/8/2026', $case->invoice_number);
        $this->assertSame('KSEF-OLD', $case->ksef_number);
        $this->assertSame('111', $case->ifirma_invoice_id);
        $this->assertNull($order->invoice_number);
        $this->assertNull($order->ifirma_invoice_id);
    }

    public function test_same_invoice_does_not_clear_ksef_while_the_number_is_still_on_the_way(): void
    {
        [$case] = $this->pair('10/8/2026', '10/8/2026', 'KSEF-1', null, '100', '100');

        $compare = app(DebtCaseInvoiceIdentityService::class)->compare($case);

        $this->assertFalse($compare['show_modal']);
        $this->assertFalse($compare['can_update']);
    }

    public function test_changed_ksef_on_the_same_invoice_can_be_updated(): void
    {
        [$case] = $this->pair('10/8/2026', '10/8/2026', 'KSEF-1', 'KSEF-2', '100', '100');

        $result = app(DebtCaseInvoiceIdentityService::class)->alignCaseToOrder($case);

        $this->assertTrue($result['success']);
        $case->refresh();
        $this->assertSame('10/8/2026', $case->invoice_number);
        $this->assertSame('KSEF-2', $case->ksef_number);
        $this->assertSame('100', $case->ifirma_invoice_id);
    }

    /**
     * @return array{0: DebtCase, 1: FormOrder}
     */
    private function pair(
        ?string $caseInvoice,
        ?string $orderInvoice,
        ?string $caseKsef,
        ?string $orderKsef,
        ?string $caseId,
        ?string $orderId,
        ?string $notes = null,
    ): array {
        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'product_price' => 100,
            'order_date' => now()->subDay(),
            'invoice_number' => $orderInvoice,
            'ksef_number' => $orderKsef,
            'ifirma_invoice_id' => $orderId,
            'notes' => $notes,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => $caseInvoice,
            'ksef_number' => $caseKsef,
            'ifirma_invoice_id' => $caseId,
            'amount_gross' => 100,
            'opened_at' => now(),
        ]);

        return [$case, $order];
    }
}
