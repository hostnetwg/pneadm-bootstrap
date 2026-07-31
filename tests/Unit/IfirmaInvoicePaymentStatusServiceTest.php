<?php

namespace Tests\Unit;

use App\Services\IfirmaInvoicePaymentStatusService;
use Tests\TestCase;

class IfirmaInvoicePaymentStatusServiceTest extends TestCase
{
    public function test_derive_status_paid_partial_unpaid_overdue(): void
    {
        $service = app(IfirmaInvoicePaymentStatusService::class);

        $this->assertSame(
            IfirmaInvoicePaymentStatusService::STATUS_PAID,
            $service->deriveStatus(365.0, 365.0, now()->subDays(5)->toDateString())
        );

        $this->assertSame(
            IfirmaInvoicePaymentStatusService::STATUS_PARTIAL,
            $service->deriveStatus(100.0, 365.0, now()->addDays(5)->toDateString())
        );

        $this->assertSame(
            IfirmaInvoicePaymentStatusService::STATUS_UNPAID,
            $service->deriveStatus(0.0, 365.0, now()->addDays(5)->toDateString())
        );

        $this->assertSame(
            IfirmaInvoicePaymentStatusService::STATUS_OVERDUE,
            $service->deriveStatus(0.0, 365.0, now()->subDays(2)->toDateString())
        );

        $this->assertSame(
            IfirmaInvoicePaymentStatusService::STATUS_UNKNOWN,
            $service->deriveStatus(0.0, null, null)
        );
    }

    public function test_snapshot_from_invoice_row(): void
    {
        $service = app(IfirmaInvoicePaymentStatusService::class);

        $snapshot = $service->snapshotFromInvoiceRow([
            'PelnyNumer' => '43/7/2026',
            'FakturaId' => 998877,
            'Zaplacono' => 0,
            'Brutto' => 365,
            'TerminPlatnosci' => now()->subDays(3)->toDateString(),
        ], 'test', null);

        $this->assertTrue($snapshot['success']);
        $this->assertSame(IfirmaInvoicePaymentStatusService::STATUS_OVERDUE, $snapshot['status']);
        $this->assertSame('998877', $snapshot['invoice_id']);
        $this->assertSame('43/7/2026', $snapshot['invoice_number']);
        $this->assertSame(0.0, $snapshot['paid_amount']);
        $this->assertSame(365.0, $snapshot['gross_amount']);
    }
}
