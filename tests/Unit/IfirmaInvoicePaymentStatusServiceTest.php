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

    public function test_snapshot_uses_wartosc_brutto_from_invoice_detail_payload(): void
    {
        $service = app(IfirmaInvoicePaymentStatusService::class);

        $snapshot = $service->snapshotFromInvoiceRow([
            'PelnyNumer' => '349/6/2026',
            'Zaplacono' => 0,
            'WartoscBrutto' => 365.0,
            'TerminPlatnosci' => now()->subDays(3)->toDateString(),
        ], 'fakturakraj/1', '130393480');

        $this->assertTrue($snapshot['success']);
        $this->assertSame(IfirmaInvoicePaymentStatusService::STATUS_OVERDUE, $snapshot['status']);
        $this->assertSame(365.0, $snapshot['gross_amount']);
    }

    public function test_derive_status_overdue_without_gross_when_due_date_known(): void
    {
        $service = app(IfirmaInvoicePaymentStatusService::class);

        $this->assertSame(
            IfirmaInvoicePaymentStatusService::STATUS_OVERDUE,
            $service->deriveStatus(0.0, null, now()->subDays(2)->toDateString())
        );
    }

    public function test_parse_issue_month_from_invoice_number(): void
    {
        $service = app(IfirmaInvoicePaymentStatusService::class);

        $june = $service->parseIssueMonthFromInvoiceNumber('239/6/2026');
        $this->assertNotNull($june);
        $this->assertSame('2026-06-01', $june->toDateString());

        $junePadded = $service->parseIssueMonthFromInvoiceNumber('12/06/2025');
        $this->assertNotNull($junePadded);
        $this->assertSame('2025-06-01', $junePadded->toDateString());

        $this->assertNull($service->parseIssueMonthFromInvoiceNumber('FV-ABC'));
        $this->assertNull($service->parseIssueMonthFromInvoiceNumber(''));
    }

    public function test_search_date_range_uses_invoice_number_month_even_when_order_is_later(): void
    {
        $service = app(IfirmaInvoicePaymentStatusService::class);

        $order = new \App\Models\FormOrder;
        $order->invoice_number = '239/6/2026';
        $order->order_date = \Carbon\Carbon::parse('2026-07-20');
        $order->created_at = \Carbon\Carbon::parse('2026-07-20');

        [$dataOd, $dataDo] = $service->resolveSearchDateRange($order);

        $this->assertTrue($dataOd <= '2026-06-01', "dataOd={$dataOd} should cover June");
        $this->assertTrue($dataDo >= '2026-06-30', "dataDo={$dataDo} should cover end of June");
        $this->assertTrue($dataDo >= '2026-07-20', "dataDo={$dataDo} should also cover order_date");
    }
}
