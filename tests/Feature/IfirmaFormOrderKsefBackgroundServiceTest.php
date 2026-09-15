<?php

namespace Tests\Feature;

use App\Jobs\FetchFormOrderKsefNumberJob;
use App\Models\FormOrder;
use App\Models\OpsRun;
use App\Models\User;
use App\Services\IfirmaApiService;
use App\Services\IfirmaFormOrderKsefBackgroundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IfirmaFormOrderKsefBackgroundServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_with_ksef_number_records_success_item(): void
    {
        Queue::fake();

        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'orderer_email' => 'ksef@example.test',
            'ifirma_invoice_id' => '555',
            'invoice_number' => '10/9/2026',
            'ksef_status' => 'pending',
        ]);

        $api = \Mockery::mock(IfirmaApiService::class)->makePartial();
        $api->shouldReceive('getInvoice')->andReturn([
            'status' => 'success',
            'data' => [
                'PelnyNumer' => '10/9/2026',
                'NumerKSeF' => '7392137630-20260915-ABCDEF000001-11',
            ],
        ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')->andReturn('7392137630-20260915-ABCDEF000001-11');
        $api->shouldReceive('extractPelnyNumerFromInvoicePayload')->andReturn('10/9/2026');
        $api->shouldReceive('unwrapInvoicePayload')->andReturnUsing(function ($payload) {
            return is_array($payload) ? $payload : [];
        });
        $api->shouldReceive('normalizeInvoiceNumber')->andReturnUsing(fn ($n) => strtolower(str_replace(' ', '', (string) $n)));
        $this->app->instance(IfirmaApiService::class, $api);

        app(IfirmaFormOrderKsefBackgroundService::class)->fetchOrReschedule($order->fresh(), 1);

        $order->refresh();
        $this->assertTrue($order->hasConfirmedKsef());
        $this->assertSame('7392137630-20260915-ABCDEF000001-11', $order->ksef_number);

        $run = OpsRun::query()->where('type', OpsRun::TYPE_KSEF_BACKGROUND)->first();
        $this->assertNotNull($run);
        $this->assertSame(1, (int) ($run->summary['numbers_received'] ?? 0));
    }

    public function test_fetch_without_number_dispatches_delayed_retry(): void
    {
        Queue::fake();

        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'orderer_email' => 'wait@example.test',
            'ifirma_invoice_id' => '556',
            'invoice_number' => '11/9/2026',
            'ksef_status' => 'pending',
        ]);

        $api = \Mockery::mock(IfirmaApiService::class)->makePartial();
        $api->shouldReceive('getInvoice')->andReturn([
            'status' => 'success',
            'data' => ['PelnyNumer' => '11/9/2026'],
        ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')->andReturn(null);
        $api->shouldReceive('detectKsefRejectionFromInvoicePayload')->andReturn(null);
        $api->shouldReceive('unwrapInvoicePayload')->andReturnUsing(function ($payload) {
            return is_array($payload) ? $payload : [];
        });
        $api->shouldReceive('normalizeInvoiceNumber')->andReturnUsing(fn ($n) => strtolower(str_replace(' ', '', (string) $n)));
        $this->app->instance(IfirmaApiService::class, $api);

        app(IfirmaFormOrderKsefBackgroundService::class)->fetchOrReschedule($order->fresh(), 1);

        $order->refresh();
        $this->assertFalse($order->hasConfirmedKsef());
        $this->assertSame('pending', $order->ksef_status);

        Queue::assertPushed(FetchFormOrderKsefNumberJob::class, function (FetchFormOrderKsefNumberJob $job) use ($order) {
            return $job->formOrderId === $order->id && $job->attempt === 2;
        });
    }

    public function test_ksef_status_endpoint_returns_awaiting_flag(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'orderer_email' => 'poll@example.test',
            'invoice_number' => '12/9/2026',
            'ifirma_invoice_id' => '557',
            'ksef_status' => 'queued',
        ]);

        $this->actingAs($user)
            ->getJson(route('form-orders.ifirma.ksef-status', $order->id))
            ->assertOk()
            ->assertJsonPath('awaiting', true)
            ->assertJsonPath('ksef_status', 'queued')
            ->assertJsonPath('ksef_number', null);
    }

    public function test_ops_reports_index_is_reachable(): void
    {
        $this->withoutVite();

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        OpsRun::query()->create([
            'type' => OpsRun::TYPE_KSEF_BACKGROUND,
            'status' => OpsRun::STATUS_RUNNING,
            'title' => 'KSeF w tle — 15.09.2026',
            'period_date' => '2026-09-15',
            'summary' => ['total' => 0],
            'started_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('ops-reports.index'))
            ->assertOk()
            ->assertSee('Raporty automat');
    }
}
