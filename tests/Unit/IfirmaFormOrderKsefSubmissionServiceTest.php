<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use App\Services\IfirmaApiService;
use App\Services\IfirmaFormOrderKsefSubmissionService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class IfirmaFormOrderKsefSubmissionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sets_ksef_email_pending_before_poll_when_send_email_true(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 501,
            'ifirma_invoice_id' => '12345',
            'invoice_number' => '50/8/2026',
            'orderer_email' => 'a@example.test',
            'ksef_email_pending' => false,
            'ksef_status' => null,
            'ksef_error' => null,
            'ksef_sent_at' => null,
            'ksef_number' => null,
        ]);
        $order->shouldReceive('isDirty')->andReturn(true);
        $order->shouldReceive('save')->andReturnTrue();

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('sendInvoiceToKsef')
            ->once()
            ->with('12345', 'fakturakraj')
            ->andReturn(['status' => 'success', 'data' => []]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')->once()->andReturn(null);
        $api->shouldReceive('waitForKsefInvoiceAccepted')
            ->once()
            ->with('12345')
            ->andReturn([
                'outcome' => 'timeout',
                'numer_ksef' => null,
                'rejection_message' => null,
                'attempts' => 12,
            ]);
        $api->shouldNotReceive('sendInvoiceByEmail');

        $request = Request::create('/test', 'POST', ['send_email' => true]);
        $service = new IfirmaFormOrderKsefSubmissionService;
        $response = $service->submit($order, $api, '12345', '50/8/2026', $request);

        $this->assertSame(504, $response->getStatusCode());
        $this->assertTrue($order->ksef_email_pending);
        $payload = $response->getData(true);
        $this->assertTrue($payload['ksef_email_pending'] ?? false);
        $this->assertSame('ksef_acceptance_timeout', $payload['step']);
    }

    public function test_clears_ksef_email_pending_after_successful_email_send(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 502,
            'ifirma_invoice_id' => '12346',
            'invoice_number' => '51/8/2026',
            'orderer_email' => 'b@example.test',
            'ksef_email_pending' => false,
            'ksef_status' => null,
            'ksef_error' => null,
            'ksef_sent_at' => null,
            'ksef_number' => null,
            'invoice_issue_date' => null,
            'invoice_due_date' => null,
        ]);
        $order->shouldReceive('isDirty')->andReturn(true);
        $order->shouldReceive('save')->andReturnTrue();
        $order->shouldReceive('update')->once()->andReturnUsing(function (array $attrs) use ($order) {
            $order->forceFill($attrs);

            return true;
        });
        $order->shouldReceive('refresh')->once()->andReturnSelf();

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('sendInvoiceToKsef')
            ->once()
            ->andReturn(['status' => 'success', 'data' => ['NumerKSeF' => '7392137630-20260805-ABCDEF000001-11']]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')
            ->once()
            ->andReturn('7392137630-20260805-ABCDEF000001-11');
        $api->shouldReceive('sendInvoiceByEmail')
            ->once()
            ->with('12346', 'b@example.test', '51/8/2026', 502, 'invoice')
            ->andReturn(['status' => 'success']);

        $request = Request::create('/test', 'POST', ['send_email' => '1']);
        $service = new IfirmaFormOrderKsefSubmissionService;
        $response = $service->submit($order, $api, '12346', '51/8/2026', $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($order->ksef_email_pending);
        $payload = $response->getData(true);
        $this->assertTrue($payload['email_sent']);
        $this->assertFalse($payload['ksef_email_pending']);
    }

    public function test_does_not_set_ksef_email_pending_when_send_email_false(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 503,
            'ifirma_invoice_id' => '12347',
            'invoice_number' => '52/8/2026',
            'orderer_email' => 'c@example.test',
            'ksef_email_pending' => false,
            'ksef_status' => null,
            'ksef_error' => null,
            'ksef_sent_at' => null,
            'ksef_number' => null,
        ]);
        $order->shouldReceive('isDirty')->andReturn(false);
        $order->shouldReceive('save')->andReturnTrue();

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('sendInvoiceToKsef')
            ->once()
            ->andReturn(['status' => 'success', 'data' => []]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')->once()->andReturn(null);
        $api->shouldReceive('waitForKsefInvoiceAccepted')
            ->once()
            ->andReturn([
                'outcome' => 'timeout',
                'numer_ksef' => null,
                'rejection_message' => null,
                'attempts' => 3,
            ]);
        $api->shouldNotReceive('sendInvoiceByEmail');

        $request = Request::create('/test', 'POST', ['send_email' => false]);
        $service = new IfirmaFormOrderKsefSubmissionService;
        $response = $service->submit($order, $api, '12347', '52/8/2026', $request);

        $this->assertSame(504, $response->getStatusCode());
        $this->assertFalse($order->ksef_email_pending);
    }

    public function test_does_not_use_ifirma_id_as_invoice_number_in_payload(): void
    {
        $order = Mockery::mock(FormOrder::class)->makePartial();
        $order->forceFill([
            'id' => 504,
            'ifirma_invoice_id' => '999001',
            'invoice_number' => '999001',
            'orderer_email' => 'd@example.test',
            'ksef_email_pending' => false,
            'ksef_status' => null,
            'ksef_error' => null,
            'ksef_sent_at' => null,
            'ksef_number' => null,
        ]);
        $order->shouldReceive('isDirty')->andReturn(false);
        $order->shouldReceive('save')->andReturnTrue();

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('sendInvoiceToKsef')
            ->once()
            ->andReturn(['status' => 'success', 'data' => []]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')->once()->andReturn(null);
        $api->shouldReceive('waitForKsefInvoiceAccepted')
            ->once()
            ->andReturn([
                'outcome' => 'timeout',
                'numer_ksef' => null,
                'rejection_message' => null,
                'attempts' => 2,
            ]);
        $api->shouldNotReceive('sendInvoiceByEmail');

        $request = Request::create('/test', 'POST', ['send_email' => false]);
        $service = new IfirmaFormOrderKsefSubmissionService;
        $response = $service->submit($order, $api, '999001', '999001', $request);

        $payload = $response->getData(true);
        $this->assertSame(504, $response->getStatusCode());
        $this->assertSame('999001', $payload['invoice_id']);
        $this->assertSame('', $payload['invoice_number']);
    }
}
