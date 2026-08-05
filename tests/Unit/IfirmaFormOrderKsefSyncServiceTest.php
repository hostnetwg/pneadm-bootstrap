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
                    ],
                ],
            ]);
        $api->shouldReceive('unwrapInvoicePayload')
            ->once()
            ->andReturn([
                'Identyfikator' => '99887766',
                'PelnyNumer' => '73/8/2026',
                'NumerKSeF' => '7392137630-20260805-ABCDEF000001-11',
            ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')
            ->once()
            ->andReturn('7392137630-20260805-ABCDEF000001-11');

        $service = new IfirmaFormOrderKsefSyncService($api);
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertTrue($result['success']);
        $this->assertSame('7392137630-20260805-ABCDEF000001-11', $result['ksef_number']);
        $this->assertSame('99887766', $result['ifirma_invoice_id']);
    }

    public function test_returns_error_when_ksef_not_yet_assigned(): void
    {
        $order = new FormOrder;
        $order->forceFill([
            'id' => 2,
            'ifirma_invoice_id' => '111',
            'ksef_number' => null,
        ]);

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getInvoice')->once()->andReturn(['status' => 'success', 'data' => []]);
        $api->shouldReceive('unwrapInvoicePayload')->once()->andReturn([
            'Identyfikator' => '111',
            'PelnyNumer' => '45/8/2026',
        ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')->once()->andReturn(null);

        $service = new IfirmaFormOrderKsefSyncService($api);
        $result = $service->syncFromIfirmaInvoiceId($order);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('nie ma jeszcze nadanego numeru KSeF', $result['message']);
    }
}
