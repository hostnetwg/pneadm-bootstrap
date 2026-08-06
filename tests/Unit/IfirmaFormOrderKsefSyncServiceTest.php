<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use App\Services\IfirmaApiService;
use App\Services\IfirmaFormOrderKsefSyncService;
use Mockery;
use Tests\TestCase;

class IfirmaFormOrderKsefSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fails_when_no_ifirma_invoice_id(): void
    {
        $order = new FormOrder;
        $order->forceFill(['id' => 1, 'ifirma_invoice_id' => null]);

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldNotReceive('getInvoice');

        $service = new IfirmaFormOrderKsefSyncService($api);
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Brak ID iFirma', $result['message']);
    }

    public function test_updates_ksef_number_from_ifirma_payload(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 7834,
            'ifirma_invoice_id' => '99887766',
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

        $service = new IfirmaFormOrderKsefSyncService($api);
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertTrue($result['success']);
        $this->assertSame('7392137630-20260805-ABCDEF000001-11', $result['ksef_number']);
        $this->assertSame('99887766', $result['ifirma_invoice_id']);
        $this->assertSame('2026-08-05', $result['invoice_issue_date']);
        $this->assertSame('2026-08-20', $result['invoice_due_date']);
        $this->assertSame('2026-08-05', $order->invoice_issue_date?->toDateString() ?? (string) $order->invoice_issue_date);
        $this->assertSame('2026-08-20', $order->invoice_due_date?->toDateString() ?? (string) $order->invoice_due_date);
    }

    public function test_updates_invoice_dates_even_when_ksef_unchanged(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 8207,
            'ifirma_invoice_id' => '555',
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

        $service = new IfirmaFormOrderKsefSyncService($api);
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['changed']);
        $this->assertSame('2026-08-06', $result['invoice_issue_date']);
        $this->assertSame('2026-08-06', $result['invoice_due_date']);
    }

    public function test_clears_ksef_when_ifirma_has_no_number(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 2,
            'ifirma_invoice_id' => '111',
            'ksef_number' => '7392137630-20260805-OLD00000001-99',
            'ksef_status' => 'sent',
            'ksef_sent_at' => now(),
            'ksef_error' => null,
        ]);
        $order->shouldReceive('save')->once()->andReturnTrue();

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getInvoice')->once()->andReturn(['status' => 'success', 'data' => []]);
        $api->shouldReceive('unwrapInvoicePayload')->once()->andReturn([
            'Identyfikator' => '111',
            'PelnyNumer' => '45/8/2026',
        ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')->once()->andReturn(null);

        $service = new IfirmaFormOrderKsefSyncService($api);
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['ksef_cleared'] ?? false);
        $this->assertNull($result['ksef_number']);
        $this->assertNull($order->ksef_number);
        $this->assertNull($order->ksef_status);
        $this->assertStringContainsString('wyczyszczono', $result['message']);
    }

    public function test_no_change_when_ifirma_has_no_ksef_and_order_already_empty(): void
    {
        $order = new FormOrder;
        $order->forceFill([
            'id' => 3,
            'ifirma_invoice_id' => '222',
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

        $service = new IfirmaFormOrderKsefSyncService($api);
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['changed'] ?? true);
        $this->assertNull($result['ksef_number']);
    }
}
