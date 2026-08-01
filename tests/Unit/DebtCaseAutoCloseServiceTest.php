<?php

namespace Tests\Unit;

use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\FormOrder;
use App\Models\User;
use App\Services\DebtCaseAutoCloseService;
use App\Services\IfirmaInvoicePaymentStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtCaseAutoCloseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_closes_open_case_when_fully_paid(): void
    {
        $user = User::factory()->create();
        $case = $this->makeCase(DebtCase::STATUS_OPEN);

        $closed = app(DebtCaseAutoCloseService::class)->closeIfFullyPaid(
            $case,
            $user,
            IfirmaInvoicePaymentStatusService::STATUS_PAID
        );

        $this->assertTrue($closed);
        $case->refresh();
        $this->assertSame(DebtCase::STATUS_CLOSED, $case->status);
        $this->assertNotNull($case->closed_at);
        $this->assertSame(DebtCaseAutoCloseService::CLOSURE_REASON, $case->closure_reason);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_CLOSE,
            'note' => DebtCaseAutoCloseService::CLOSURE_REASON,
        ]);
    }

    public function test_does_not_close_disputed_or_already_closed(): void
    {
        $service = app(DebtCaseAutoCloseService::class);

        $disputed = $this->makeCase(DebtCase::STATUS_DISPUTED);
        $this->assertFalse($service->closeIfFullyPaid(
            $disputed,
            null,
            IfirmaInvoicePaymentStatusService::STATUS_PAID
        ));
        $this->assertSame(DebtCase::STATUS_DISPUTED, $disputed->fresh()->status);

        $closed = $this->makeCase(DebtCase::STATUS_CLOSED, ['closed_at' => now()]);
        $this->assertFalse($service->closeIfFullyPaid(
            $closed,
            null,
            IfirmaInvoicePaymentStatusService::STATUS_PAID
        ));
        $this->assertDatabaseMissing('debt_case_actions', [
            'debt_case_id' => $closed->id,
            'action_type' => DebtCaseAction::TYPE_CLOSE,
        ]);
    }

    public function test_does_not_close_when_not_fully_paid(): void
    {
        $case = $this->makeCase(DebtCase::STATUS_IN_PROGRESS);

        $closed = app(DebtCaseAutoCloseService::class)->closeIfFullyPaid(
            $case,
            null,
            IfirmaInvoicePaymentStatusService::STATUS_PARTIAL
        );

        $this->assertFalse($closed);
        $this->assertSame(DebtCase::STATUS_IN_PROGRESS, $case->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCase(string $status, array $overrides = []): DebtCase
    {
        $order = FormOrder::create([
            'product_name' => 'Test',
            'product_price' => 100,
            'order_date' => now(),
            'invoice_number' => '1/1/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);

        return DebtCase::create(array_merge([
            'form_order_id' => $order->id,
            'status' => $status,
            'priority' => DebtCase::PRIORITY_NORMAL,
            'customer_segment' => DebtCase::SEGMENT_STANDARD,
            'risk_score' => 0,
            'relationship_score' => 0,
            'invoice_number' => $order->invoice_number,
            'amount_gross' => 100,
            'opened_at' => now(),
        ], $overrides));
    }
}
