<?php

namespace Tests\Feature;

use App\Jobs\FetchFormOrderKsefNumberJob;
use App\Jobs\SubmitFormOrderToKsefJob;
use App\Models\FormOrder;
use App\Models\OpsRun;
use App\Models\User;
use App\Services\IfirmaApiService;
use App\Services\IfirmaFormOrderKsefBackgroundService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IfirmaFormOrderKsefBackgroundServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_jobs_do_not_touch_replacement_invoice(): void
    {
        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'orderer_email' => 'replacement@example.test',
            'ifirma_invoice_id' => 'NEW-ID',
            'invoice_number' => '689/9/2026',
            'ksef_status' => 'pending',
        ]);

        $background = \Mockery::mock(IfirmaFormOrderKsefBackgroundService::class);
        $background->shouldNotReceive('sendAndScheduleFetch');
        $background->shouldNotReceive('fetchOrReschedule');

        (new SubmitFormOrderToKsefJob($order->id, 'OLD-ID'))->handle($background);
        (new FetchFormOrderKsefNumberJob($order->id, 2, 'OLD-ID'))->handle($background);
    }

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
        $api->shouldReceive('getInvoice')->once()->andReturn([
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
        $this->travelTo(Carbon::parse('2026-09-30 09:00:00', 'Europe/Warsaw'));
        config()->set('services.ifirma.ksef_background_retry_delays_seconds', [60, 300]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'orderer_email' => 'wait@example.test',
            'ifirma_invoice_id' => '556',
            'invoice_number' => '11/9/2026',
            'ksef_status' => 'pending',
        ]);

        $api = \Mockery::mock(IfirmaApiService::class)->makePartial();
        $api->shouldReceive('getInvoice')->once()->andReturn([
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
            return $job->formOrderId === $order->id
                && $job->attempt === 2
                && $job->queuedAt === now()->toIso8601String()
                && $job->scheduledFor === now()->addSeconds(60)->toIso8601String()
                && $job->delay?->getTimestamp() === now()->addSeconds(60)->getTimestamp();
        });
    }

    public function test_fetch_stops_after_sparse_retry_schedule_is_exhausted(): void
    {
        Queue::fake();
        config()->set('services.ifirma.ksef_background_retry_delays_seconds', [60]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'orderer_email' => 'wait-final@example.test',
            'ifirma_invoice_id' => '558',
            'invoice_number' => '13/9/2026',
            'ksef_status' => 'pending',
        ]);

        $api = \Mockery::mock(IfirmaApiService::class)->makePartial();
        $api->shouldReceive('getInvoice')->once()->andReturn([
            'status' => 'success',
            'data' => ['PelnyNumer' => '13/9/2026'],
        ]);
        $api->shouldReceive('extractNumerKSeFFromInvoicePayload')->andReturn(null);
        $api->shouldReceive('detectKsefRejectionFromInvoicePayload')->andReturn(null);
        $api->shouldReceive('unwrapInvoicePayload')->andReturnUsing(function ($payload) {
            return is_array($payload) ? $payload : [];
        });
        $api->shouldReceive('normalizeInvoiceNumber')->andReturnUsing(fn ($n) => strtolower(str_replace(' ', '', (string) $n)));
        $this->app->instance(IfirmaApiService::class, $api);

        app(IfirmaFormOrderKsefBackgroundService::class)->fetchOrReschedule($order->fresh(), 2);

        Queue::assertNotPushed(FetchFormOrderKsefNumberJob::class);
        $this->assertStringContainsString('nie nadało jeszcze numeru KSeF', (string) $order->fresh()->ksef_error);
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
            'ksef_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->getJson(route('form-orders.ifirma.ksef-status', $order->id))
            ->assertOk()
            ->assertJsonPath('awaiting', true)
            ->assertJsonPath('ksef_status', 'pending')
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
