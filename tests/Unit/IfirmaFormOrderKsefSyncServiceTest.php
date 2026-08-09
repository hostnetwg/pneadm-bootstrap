<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use App\Services\IfirmaApiService;
use App\Services\IfirmaFormOrderKsefSyncService;
use App\Services\IfirmaInvoicePaymentStatusService;
use Mockery;
use Tests\TestCase;

class IfirmaFormOrderKsefSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fails_when_no_ifirma_invoice_id_and_no_invoice_number(): void
    {
        $order = new FormOrder;
        $order->forceFill(['id' => 1, 'ifirma_invoice_id' => null, 'invoice_number' => null]);

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldNotReceive('getInvoice');
        $paymentStatus = Mockery::mock(IfirmaInvoicePaymentStatusService::class);
        $paymentStatus->shouldNotReceive('fetchPaymentSnapshotForOrder');

        $service = new IfirmaFormOrderKsefSyncService($api, $paymentStatus);
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Brak ID iFirma i numeru FV', $result['message']);
    }

    public function test_resolves_ifirma_id_from_invoice_number_then_syncs_dates_and_ksef(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 8361,
            'ifirma_invoice_id' => null,
            'invoice_number' => '12/8/2026',
            'ksef_number' => null,
            'ksef_status' => null,
            'ksef_sent_at' => null,
            'ksef_error' => null,
            'invoice_issue_date' => null,
            'invoice_due_date' => null,
            'product_price' => 365,
        ]);
        $order->shouldReceive('save')->once()->andReturnTrue();

        $paymentStatus = Mockery::mock(IfirmaInvoicePaymentStatusService::class);
        $paymentStatus->shouldReceive('fetchPaymentSnapshotForOrder')
            ->once()
            ->andReturn([
                'success' => true,
                'invoice_id' => '654321',
                'source' => 'faktury.json',
            ]);

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getInvoice')
            ->once()
            ->with('654321')
            ->andReturn(['status' => 'success', 'data' => []]);
        $api->shouldReceive('unwrapInvoicePayload')
            ->once()
            ->andReturn([
                'Identyfikator' => '654321',
                'PelnyNumer' => '12/8/2026',
                'NumerKSeF' => '7392137630-20260805-ABCDEF000001-11',
                'DataWystawienia' => '2026-08-05',
                'TerminPlatnosci' => '2026-08-20',
            ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')
            ->once()
            ->andReturn('7392137630-20260805-ABCDEF000001-11');
        $api->shouldReceive('normalizeInvoiceNumber')->andReturnUsing(fn ($n) => str_replace(' ', '', (string) $n));

        $service = new IfirmaFormOrderKsefSyncService($api, $paymentStatus);
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['changed']);
        $this->assertSame('654321', $result['ifirma_invoice_id']);
        $this->assertSame('654321', $order->ifirma_invoice_id);
        $this->assertSame('12/8/2026', $order->invoice_number);
        $this->assertSame('2026-08-05', $result['invoice_issue_date']);
        $this->assertSame('2026-08-20', $result['invoice_due_date']);
        $this->assertSame('7392137630-20260805-ABCDEF000001-11', $result['ksef_number']);
    }

    public function test_prefer_number_lookup_replaces_stale_ifirma_id(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 8361,
            'ifirma_invoice_id' => 'OLD-DELETED',
            'invoice_number' => '277/8/2026',
            'ksef_number' => null,
            'ksef_status' => null,
            'ksef_sent_at' => null,
            'invoice_issue_date' => null,
            'invoice_due_date' => null,
            'product_price' => 200,
        ]);
        $order->shouldReceive('save')->once()->andReturnTrue();

        $paymentStatus = Mockery::mock(IfirmaInvoicePaymentStatusService::class);
        $paymentStatus->shouldReceive('fetchPaymentSnapshotForOrder')
            ->once()
            ->andReturn([
                'success' => true,
                'invoice_id' => 'NEW-ID',
                'source' => 'faktury.json',
            ]);

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getInvoice')
            ->once()
            ->with('NEW-ID')
            ->andReturn(['status' => 'success', 'data' => []]);
        $api->shouldReceive('unwrapInvoicePayload')->once()->andReturn([
            'Identyfikator' => 'NEW-ID',
            'PelnyNumer' => '277/8/2026',
            'NumerKSeF' => '7392137630-20260809-ZZZZZZ000001-22',
            'DataWystawienia' => '2026-08-09',
            'TerminPlatnosci' => '2026-08-23',
        ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')
            ->once()
            ->andReturn('7392137630-20260809-ZZZZZZ000001-22');
        $api->shouldReceive('normalizeInvoiceNumber')->andReturnUsing(fn ($n) => (string) $n);

        $service = new IfirmaFormOrderKsefSyncService($api, $paymentStatus);
        $result = $service->syncFromIfirmaInvoiceId($order, '277/8/2026', true);

        $this->assertTrue($result['success']);
        $this->assertSame('NEW-ID', $order->ifirma_invoice_id);
        $this->assertSame('2026-08-09', $result['invoice_issue_date']);
    }

    public function test_does_not_clear_local_ksef_when_ifirma_has_none(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 2,
            'ifirma_invoice_id' => '111',
            'invoice_number' => '45/8/2026',
            'ksef_number' => '7392137630-20260805-MANUAL00001-99',
            'ksef_status' => 'sent',
            'ksef_sent_at' => now(),
            'ksef_error' => null,
        ]);
        $order->shouldReceive('save')->never();

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getInvoice')->once()->andReturn(['status' => 'success', 'data' => []]);
        $api->shouldReceive('unwrapInvoicePayload')->once()->andReturn([
            'Identyfikator' => '111',
            'PelnyNumer' => '45/8/2026',
        ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')->once()->andReturn(null);
        $api->shouldReceive('normalizeInvoiceNumber')->andReturnUsing(fn ($n) => (string) $n);

        $service = new IfirmaFormOrderKsefSyncService($api, Mockery::mock(IfirmaInvoicePaymentStatusService::class));
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['ksef_cleared'] ?? true);
        $this->assertSame('7392137630-20260805-MANUAL00001-99', $order->ksef_number);
        $this->assertStringContainsString('brak numeru KSeF', $result['message']);
    }

    public function test_does_not_overwrite_manual_invoice_number(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 9,
            'ifirma_invoice_id' => '111',
            'invoice_number' => 'MOJ-RECZNY/1',
            'ksef_number' => null,
            'ksef_status' => null,
            'ksef_sent_at' => null,
            'ksef_error' => null,
            'invoice_issue_date' => null,
            'invoice_due_date' => null,
        ]);
        $order->shouldReceive('save')->once()->andReturnTrue();

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getInvoice')->once()->andReturn(['status' => 'success', 'data' => []]);
        $api->shouldReceive('unwrapInvoicePayload')->once()->andReturn([
            'Identyfikator' => '111',
            'PelnyNumer' => '99/8/2026',
            'NumerKSeF' => '7392137630-20260805-ABCDEF000001-11',
            'DataWystawienia' => '2026-08-01',
            'TerminPlatnosci' => '2026-08-15',
        ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')
            ->once()
            ->andReturn('7392137630-20260805-ABCDEF000001-11');
        $api->shouldReceive('normalizeInvoiceNumber')->andReturnUsing(fn ($n) => (string) $n);

        $service = new IfirmaFormOrderKsefSyncService($api, Mockery::mock(IfirmaInvoicePaymentStatusService::class));
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertTrue($result['success']);
        $this->assertSame('MOJ-RECZNY/1', $order->invoice_number);
        $this->assertSame('MOJ-RECZNY/1', $result['invoice_number']);
    }

    public function test_updates_ksef_number_from_ifirma_payload(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 7834,
            'ifirma_invoice_id' => '99887766',
            'invoice_number' => '73/8/2026',
            'ksef_number' => null,
            'ksef_status' => null,
            'ksef_sent_at' => null,
            'ksef_error' => null,
            'invoice_issue_date' => null,
            'invoice_due_date' => null,
        ]);
        $order->shouldReceive('save')->once()->andReturnTrue();

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getInvoice')
            ->once()
            ->with('99887766')
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'response' => [
                        'Identyfikator' => '99887766',
                        'PelnyNumer' => '73/8/2026',
                        'NumerKSeF' => '7392137630-20260805-ABCDEF000001-11',
                        'DataWystawienia' => '2026-08-05',
                        'TerminPlatnosci' => '2026-08-20',
                    ],
                ],
            ]);
        $api->shouldReceive('unwrapInvoicePayload')
            ->once()
            ->andReturn([
                'Identyfikator' => '99887766',
                'PelnyNumer' => '73/8/2026',
                'NumerKSeF' => '7392137630-20260805-ABCDEF000001-11',
                'DataWystawienia' => '2026-08-05',
                'TerminPlatnosci' => '2026-08-20',
            ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')
            ->once()
            ->andReturn('7392137630-20260805-ABCDEF000001-11');
        $api->shouldReceive('normalizeInvoiceNumber')->andReturnUsing(fn ($n) => (string) $n);

        $service = new IfirmaFormOrderKsefSyncService($api, Mockery::mock(IfirmaInvoicePaymentStatusService::class));
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertTrue($result['success']);
        $this->assertSame('7392137630-20260805-ABCDEF000001-11', $result['ksef_number']);
        $this->assertSame('99887766', $result['ifirma_invoice_id']);
        $this->assertSame('2026-08-05', $result['invoice_issue_date']);
        $this->assertSame('2026-08-20', $result['invoice_due_date']);
    }

    public function test_updates_invoice_dates_even_when_ksef_unchanged(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 8207,
            'ifirma_invoice_id' => '555',
            'invoice_number' => '12/8/2026',
            'ksef_number' => '7392137630-20260805-ABCDEF000001-11',
            'ksef_status' => 'sent',
            'ksef_sent_at' => now(),
            'ksef_error' => null,
            'invoice_issue_date' => '2026-07-01',
            'invoice_due_date' => '2026-07-01',
        ]);
        $order->shouldReceive('save')->once()->andReturnTrue();

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getInvoice')->once()->andReturn(['status' => 'success', 'data' => []]);
        $api->shouldReceive('unwrapInvoicePayload')->once()->andReturn([
            'Identyfikator' => '555',
            'PelnyNumer' => '12/8/2026',
            'NumerKSeF' => '7392137630-20260805-ABCDEF000001-11',
            'DataWystawienia' => '2026-08-06',
            'TerminPlatnosci' => '2026-08-06',
        ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')
            ->once()
            ->andReturn('7392137630-20260805-ABCDEF000001-11');
        $api->shouldReceive('normalizeInvoiceNumber')->andReturnUsing(fn ($n) => (string) $n);

        $service = new IfirmaFormOrderKsefSyncService($api, Mockery::mock(IfirmaInvoicePaymentStatusService::class));
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['changed']);
        $this->assertSame('2026-08-06', $result['invoice_issue_date']);
        $this->assertSame('2026-08-06', $result['invoice_due_date']);
    }

    public function test_no_change_when_ifirma_has_no_ksef_and_order_already_empty(): void
    {
        $order = new FormOrder;
        $order->forceFill([
            'id' => 3,
            'ifirma_invoice_id' => '222',
            'invoice_number' => '46/8/2026',
            'ksef_number' => null,
            'ksef_status' => null,
        ]);

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getInvoice')->once()->andReturn(['status' => 'success', 'data' => []]);
        $api->shouldReceive('unwrapInvoicePayload')->once()->andReturn([
            'Identyfikator' => '222',
            'PelnyNumer' => '46/8/2026',
        ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')->once()->andReturn(null);
        $api->shouldReceive('normalizeInvoiceNumber')->andReturnUsing(fn ($n) => (string) $n);

        $service = new IfirmaFormOrderKsefSyncService($api, Mockery::mock(IfirmaInvoicePaymentStatusService::class));
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['changed'] ?? true);
        $this->assertNull($result['ksef_number']);
    }
}
