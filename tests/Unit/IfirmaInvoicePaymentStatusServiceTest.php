<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use App\Services\IfirmaApiService;
use App\Services\IfirmaInvoicePaymentStatusService;
use Mockery;
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

        $order = new FormOrder;
        $order->invoice_number = '239/6/2026';
        $order->order_date = \Carbon\Carbon::parse('2026-07-20');
        $order->created_at = \Carbon\Carbon::parse('2026-07-20');

        [$dataOd, $dataDo] = $service->resolveSearchDateRange($order);

        $this->assertTrue($dataOd <= '2026-06-01', "dataOd={$dataOd} should cover June");
        $this->assertTrue($dataDo >= '2026-06-30', "dataDo={$dataDo} should cover end of June");
        $this->assertTrue($dataDo >= '2026-07-20', "dataDo={$dataDo} should also cover order_date");
    }

    public function test_wide_search_date_range_uses_larger_padding(): void
    {
        $service = app(IfirmaInvoicePaymentStatusService::class);

        $order = new FormOrder;
        $order->invoice_number = '40/6/2026';

        [$narrowFrom, $narrowTo] = $service->resolveSearchDateRange($order);
        [$wideFrom, $wideTo] = $service->resolveSearchDateRange($order, wide: true);

        $this->assertTrue($wideFrom < $narrowFrom, "wideFrom={$wideFrom} narrowFrom={$narrowFrom}");
        $this->assertTrue($wideTo > $narrowTo, "wideTo={$wideTo} narrowTo={$narrowTo}");
        $this->assertSame('2026-04-02', $wideFrom); // 2026-06-01 - 60
        $this->assertSame('2026-10-28', $wideTo); // 2026-06-30 + 120
    }

    public function test_fetch_snapshot_resolves_id_from_list_then_loads_invoice_detail(): void
    {
        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('findSalesInvoiceByPelnyNumer')
            ->once()
            ->andReturn([
                'status' => 'success',
                'invoice' => [
                    'PelnyNumer' => '40/6/2026',
                    'FakturaId' => 131666482,
                    'Zaplacono' => 0,
                    'Brutto' => 365,
                    'TerminPlatnosci' => '2026-06-15',
                ],
            ]);
        $api->shouldReceive('getInvoice')
            ->once()
            ->with('131666482')
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'PelnyNumer' => '40/6/2026',
                    'Zaplacono' => 365,
                    'WartoscBrutto' => 365,
                    'DataWystawienia' => '2026-06-01',
                    'TerminPlatnosci' => '2026-06-15',
                    'FakturaId' => 131666482,
                ],
            ]);
        $api->shouldReceive('unwrapInvoicePayload')->andReturnUsing(fn ($d) => is_array($d) ? $d : []);

        $service = new IfirmaInvoicePaymentStatusService($api);

        $order = new FormOrder;
        $order->invoice_number = '40/6/2026';
        $order->order_date = \Carbon\Carbon::parse('2026-06-01');

        $snapshot = $service->fetchPaymentSnapshotForOrder($order);

        $this->assertTrue($snapshot['success']);
        $this->assertSame(IfirmaInvoicePaymentStatusService::STATUS_PAID, $snapshot['status']);
        $this->assertSame('131666482', $snapshot['invoice_id']);
        $this->assertSame(365.0, $snapshot['paid_amount']);
        $this->assertStringContainsString('fakturakraj/131666482', (string) $snapshot['source']);
    }

    public function test_fetch_snapshot_falls_back_to_list_row_when_detail_fails(): void
    {
        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('findSalesInvoiceByPelnyNumer')
            ->once()
            ->andReturn([
                'status' => 'success',
                'invoice' => [
                    'PelnyNumer' => '40/6/2026',
                    'FakturaId' => 131666482,
                    'Zaplacono' => 100,
                    'Brutto' => 365,
                    'TerminPlatnosci' => now()->addDays(10)->toDateString(),
                ],
            ]);
        $api->shouldReceive('getInvoice')
            ->once()
            ->with('131666482')
            ->andReturn([
                'status' => 'error',
                'message' => 'timeout',
            ]);

        $service = new IfirmaInvoicePaymentStatusService($api);

        $order = new FormOrder;
        $order->invoice_number = '40/6/2026';

        $snapshot = $service->fetchPaymentSnapshotForOrder($order);

        $this->assertTrue($snapshot['success']);
        $this->assertSame(IfirmaInvoicePaymentStatusService::STATUS_PARTIAL, $snapshot['status']);
        $this->assertSame('faktury.json (PelnyNumer)', $snapshot['source']);
        $this->assertSame('131666482', $snapshot['invoice_id']);
    }
}
