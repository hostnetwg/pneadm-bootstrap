<?php

namespace Tests\Unit;

use App\Jobs\SubmitFormOrderToKsefJob;
use App\Models\FormOrder;
use App\Models\OpsRun;
use App\Models\OpsRunItem;
use App\Services\IfirmaFormOrderKsefBackgroundService;
use App\Services\IfirmaFormOrderKsefSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IfirmaFormOrderKsefSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_queues_background_job_and_sets_pending_email_flag(): void
    {
        Queue::fake();

        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'orderer_email' => 'a@example.test',
            'ifirma_invoice_id' => '12345',
            'invoice_number' => '50/8/2026',
            'ksef_email_pending' => false,
        ]);

        $request = Request::create('/test', 'POST', ['send_email' => true]);
        $service = app(IfirmaFormOrderKsefSubmissionService::class);
        $response = $service->submit($order, app(\App\Services\IfirmaApiService::class), '12345', '50/8/2026', $request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['ksef_queued']);
        $this->assertSame('pending', $payload['ksef_status']);
        $this->assertTrue($payload['ksef_email_pending']);
        $this->assertSame('50/8/2026', $payload['invoice_number']);

        $order->refresh();
        $this->assertTrue((bool) $order->ksef_email_pending);
        $this->assertSame('pending', $order->ksef_status);

        Queue::assertPushed(SubmitFormOrderToKsefJob::class, function (SubmitFormOrderToKsefJob $job) use ($order) {
            return $job->formOrderId === $order->id;
        });

        $this->assertTrue(OpsRun::query()->where('type', OpsRun::TYPE_KSEF_BACKGROUND)->exists());
        $this->assertTrue(OpsRunItem::query()->where('subject_id', $order->id)->exists());
    }

    public function test_does_not_set_ksef_email_pending_when_send_email_false(): void
    {
        Queue::fake();

        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'orderer_email' => 'c@example.test',
            'ifirma_invoice_id' => '12347',
            'invoice_number' => '52/8/2026',
            'ksef_email_pending' => false,
        ]);

        $request = Request::create('/test', 'POST', ['send_email' => false]);
        $service = app(IfirmaFormOrderKsefSubmissionService::class);
        $response = $service->submit($order, app(\App\Services\IfirmaApiService::class), '12347', '52/8/2026', $request);

        $this->assertSame(200, $response->getStatusCode());
        $order->refresh();
        $this->assertFalse((bool) $order->ksef_email_pending);
        $this->assertSame('pending', $order->ksef_status);
    }

    public function test_does_not_use_ifirma_id_as_invoice_number_in_payload(): void
    {
        Queue::fake();

        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'orderer_email' => 'd@example.test',
            'ifirma_invoice_id' => '999001',
            'invoice_number' => '999001',
        ]);

        $request = Request::create('/test', 'POST', ['send_email' => false]);
        $service = app(IfirmaFormOrderKsefSubmissionService::class);
        $response = $service->submit($order, app(\App\Services\IfirmaApiService::class), '999001', '999001', $request);

        $payload = $response->getData(true);
        $this->assertSame('999001', $payload['invoice_id']);
        $this->assertSame('', $payload['invoice_number']);
    }

    public function test_background_send_failure_marks_order_failed(): void
    {
        Queue::fake();

        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'orderer_email' => 'fail@example.test',
            'ifirma_invoice_id' => '888',
            'invoice_number' => '1/9/2026',
            'ksef_status' => 'pending',
        ]);

        $api = \Mockery::mock(\App\Services\IfirmaApiService::class);
        $api->shouldReceive('sendInvoiceToKsef')->once()->andReturn([
            'status' => 'error',
            'message' => 'Brak uprawnień KSeF',
        ]);
        $api->shouldReceive('getInvoice')->once()->andReturn(['status' => 'error']);
        $this->app->instance(\App\Services\IfirmaApiService::class, $api);

        app(IfirmaFormOrderKsefBackgroundService::class)->sendAndScheduleFetch($order->fresh());

        $order->refresh();
        $this->assertSame('failed', $order->ksef_status);
        $this->assertStringContainsString('Brak uprawnień', (string) $order->ksef_error);
    }
}
