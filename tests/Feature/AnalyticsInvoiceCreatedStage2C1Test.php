<?php

namespace Tests\Feature;

use App\Enums\Analytics\AnalyticsEventName;
use App\Jobs\Analytics\StoreAnalyticsEventJob;
use App\Models\Analytics\AnalyticsEvent;
use App\Models\FormOrder;
use App\Services\Analytics\AnalyticsPayloadSanitizer;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\InvoiceAnalyticsTracker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Etap 2C-1: event invoice_created (ADR-005).
 *
 * Testy działają na bazie SQLite in-memory (połączenie `mysql` dla form_orders
 * oraz `analytics` dla analytics_events), aby observer FormOrder odpalał się
 * naturalnie przez Eloquent.
 */
class AnalyticsInvoiceCreatedStage2C1Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('analytics.enabled', true);
        config()->set('analytics.default_mode', 'standard');
        config()->set('analytics.sample_rate', 100);
        config()->set('analytics.queue.connection', 'sync');
        config()->set('analytics.queue.name', 'analytics');

        config()->set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.connections.analytics', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('mysql');
        DB::purge('analytics');

        $this->createFormOrdersTable();
        $this->createAnalyticsEventsTable();

        // Wyczyść ewentualny zalegający hint źródła z innych testów.
        InvoiceAnalyticsTracker::consumeSourceHint();
    }

    protected function tearDown(): void
    {
        InvoiceAnalyticsTracker::consumeSourceHint();

        parent::tearDown();
    }

    // --- Trigger: empty -> present ---

    public function test_invoice_created_dispatched_on_first_valid_invoice_number(): void
    {
        Queue::fake();

        $order = $this->createOrder();
        $order->invoice_number = 'FV/2026/01';
        $order->save();

        Queue::assertPushed(StoreAnalyticsEventJob::class, function (StoreAnalyticsEventJob $job): bool {
            return ($job->payload['event_name'] ?? null) === AnalyticsEventName::InvoiceCreated->value;
        });
    }

    public function test_not_dispatched_when_invoice_number_stays_empty(): void
    {
        Queue::fake();

        $order = $this->createOrder();
        $order->status_completed = 1;
        $order->save();

        $this->assertNoInvoiceCreatedPushed();
    }

    public function test_not_dispatched_for_empty_string(): void
    {
        Queue::fake();

        $order = $this->createOrder();
        $order->invoice_number = '';
        $order->save();

        $this->assertNoInvoiceCreatedPushed();
    }

    public function test_not_dispatched_for_zero_string(): void
    {
        Queue::fake();

        $order = $this->createOrder();
        $order->invoice_number = '0';
        $order->save();

        $this->assertNoInvoiceCreatedPushed();
    }

    public function test_not_dispatched_when_changing_one_valid_number_to_another(): void
    {
        $order = $this->createOrder();
        $order->invoice_number = 'FV/2026/01';
        $order->save();

        Queue::fake();

        $order->invoice_number = 'FV/2026/02';
        $order->save();

        $this->assertNoInvoiceCreatedPushed();
    }

    public function test_pro_forma_in_notes_does_not_trigger_invoice_created(): void
    {
        Queue::fake();

        $order = $this->createOrder();
        $order->notes = 'PRO-FORMA: PF/2026/01';
        $order->save();

        $this->assertNoInvoiceCreatedPushed();
    }

    // --- Idempotencja ---

    public function test_event_uuid_is_deterministic(): void
    {
        Queue::fake();

        $order = $this->createOrder();
        $order->invoice_number = 'FV/2026/01';
        $order->save();

        $payload = $this->capturePushedPayload();
        $this->assertSame('invoice_created|'.$order->id, $payload['event_uuid'] ?? null);
    }

    public function test_duplicate_payload_does_not_create_second_row(): void
    {
        $payload = [
            'event_uuid' => 'invoice_created|55',
            'event_name' => AnalyticsEventName::InvoiceCreated->value,
            'event_category' => 'invoice',
            'occurred_at' => now()->toDateTimeString(),
            'app_source' => 'pneadm',
            'form_order_id' => 55,
            'course_id' => 10,
            'metadata' => ['order_flow' => 'deferred', 'invoice_path_type' => 'manual', 'amount_gross' => 100.0],
        ];

        $sanitizer = app(AnalyticsPayloadSanitizer::class);
        (new StoreAnalyticsEventJob($payload))->handle($sanitizer);
        (new StoreAnalyticsEventJob($payload))->handle($sanitizer);

        $this->assertSame(1, AnalyticsEvent::query()->where('event_uuid', 'invoice_created|55')->count());
    }

    // --- Payload ---

    public function test_payload_contains_core_safe_fields(): void
    {
        Queue::fake();

        $order = $this->createOrder([
            'product_id' => 321,
            'product_price' => 199.99,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
        ]);
        $order->invoice_number = 'FV/2026/07';
        $order->save();

        $payload = $this->capturePushedPayload();

        $this->assertSame($order->id, $payload['form_order_id'] ?? null);
        $this->assertSame(321, $payload['course_id'] ?? null);
        $this->assertSame('deferred', $payload['metadata']['order_flow'] ?? null);
        $this->assertSame('unknown', $payload['metadata']['invoice_path_type'] ?? null);
        $this->assertSame(199.99, $payload['metadata']['amount_gross'] ?? null);
    }

    public function test_order_flow_is_deferred_for_deferred_order(): void
    {
        Queue::fake();

        $order = $this->createOrder(['payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE]);
        $order->invoice_number = 'FV/2026/08';
        $order->save();

        $this->assertSame('deferred', $this->capturePushedPayload()['metadata']['order_flow'] ?? null);
    }

    public function test_order_flow_is_online_for_online_order_with_later_invoice(): void
    {
        Queue::fake();

        $order = $this->createOrder(['payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY]);
        $order->invoice_number = 'FV/2026/09';
        $order->save();

        $this->assertSame('online', $this->capturePushedPayload()['metadata']['order_flow'] ?? null);
    }

    public function test_invoice_path_type_manual_from_hint(): void
    {
        Queue::fake();

        $order = $this->createOrder();
        InvoiceAnalyticsTracker::hintSource(InvoiceAnalyticsTracker::PATH_MANUAL);
        $order->invoice_number = 'FV/2026/10';
        $order->save();

        $this->assertSame('manual', $this->capturePushedPayload()['metadata']['invoice_path_type'] ?? null);
    }

    public function test_invoice_path_type_ifirma_from_hint(): void
    {
        Queue::fake();

        $order = $this->createOrder();
        InvoiceAnalyticsTracker::hintSource(InvoiceAnalyticsTracker::PATH_IFIRMA);
        $order->invoice_number = 'FV/2026/11';
        $order->save();

        $this->assertSame('ifirma', $this->capturePushedPayload()['metadata']['invoice_path_type'] ?? null);
    }

    // --- RODO ---

    public function test_payload_has_no_pii_or_invoice_or_raw_data(): void
    {
        Queue::fake();

        $order = $this->createOrder([
            'product_id' => 10,
            'product_price' => 250.00,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'buyer_name' => 'SECRET_BUYER_NAME',
            'buyer_nip' => '9999999999',
            'buyer_address' => 'SECRET_ADDRESS_STREET',
            'recipient_name' => 'SECRET_RECIPIENT_NAME',
            'orderer_email' => 'secret@example.com',
            'orderer_phone' => '600100200',
        ]);
        $order->invoice_number = 'FV/SECRET/123';
        $order->save();

        $payload = $this->capturePushedPayload();
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        foreach ([
            'FV/SECRET/123',
            'SECRET_BUYER_NAME',
            '9999999999',
            'SECRET_ADDRESS_STREET',
            'SECRET_RECIPIENT_NAME',
            'secret@example.com',
            '600100200',
        ] as $sentinel) {
            $this->assertStringNotContainsString($sentinel, $json);
        }

        $this->assertFalse(app(AnalyticsPayloadSanitizer::class)->containsForbiddenKeys($payload));

        $this->assertArrayNotHasKey('invoice_number', $payload);
        $this->assertArrayNotHasKey('buyer_nip', $payload);
        $this->assertArrayNotHasKey('buyer_name', $payload);
        $this->assertSame(
            ['order_flow', 'invoice_path_type', 'payment_type', 'amount_gross'],
            array_keys($payload['metadata']),
        );
    }

    // --- Tryby / wyłączenie ---

    public function test_not_dispatched_when_analytics_disabled(): void
    {
        config()->set('analytics.enabled', false);
        Queue::fake();

        $order = $this->createOrder();
        $order->invoice_number = 'FV/2026/12';
        $order->save();

        $this->assertNoInvoiceCreatedPushed();
    }

    public function test_not_dispatched_when_mode_off(): void
    {
        config()->set('analytics.default_mode', 'off');
        Queue::fake();

        $order = $this->createOrder();
        $order->invoice_number = 'FV/2026/13';
        $order->save();

        $this->assertNoInvoiceCreatedPushed();
    }

    // --- Fail-silent ---

    public function test_analytics_failure_does_not_break_invoice_number_save(): void
    {
        $throwing = Mockery::mock(InvoiceAnalyticsTracker::class);
        $throwing->shouldReceive('trackInvoiceCreated')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(InvoiceAnalyticsTracker::class, $throwing);

        $order = $this->createOrder();
        $order->invoice_number = 'FV/2026/14';
        $order->save();

        $this->assertSame(
            'FV/2026/14',
            FormOrder::query()->whereKey($order->id)->value('invoice_number'),
        );
    }

    // --- Helpers ---

    private function createOrder(array $overrides = []): FormOrder
    {
        return FormOrder::query()->create(array_merge([
            'product_id' => 100,
            'product_price' => 150.00,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
        ], $overrides));
    }

    private function capturePushedPayload(): array
    {
        $payload = [];

        Queue::assertPushed(StoreAnalyticsEventJob::class, function (StoreAnalyticsEventJob $job) use (&$payload): bool {
            if (($job->payload['event_name'] ?? null) === AnalyticsEventName::InvoiceCreated->value) {
                $payload = $job->payload;

                return true;
            }

            return false;
        });

        return $payload;
    }

    private function assertNoInvoiceCreatedPushed(): void
    {
        Queue::assertNotPushed(StoreAnalyticsEventJob::class, function (StoreAnalyticsEventJob $job): bool {
            return ($job->payload['event_name'] ?? null) === AnalyticsEventName::InvoiceCreated->value;
        });
    }

    private function createFormOrdersTable(): void
    {
        Schema::connection('mysql')->dropIfExists('form_orders');

        Schema::connection('mysql')->create('form_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('product_price', 10, 2)->nullable();
            $table->string('payment_mode')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_nip')->nullable();
            $table->string('buyer_address')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('orderer_email')->nullable();
            $table->string('orderer_phone')->nullable();
            $table->text('notes')->nullable();
            $table->integer('publigo_sent')->default(0);
            $table->integer('status_completed')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createAnalyticsEventsTable(): void
    {
        Schema::connection('analytics')->dropIfExists('analytics_events');

        Schema::connection('analytics')->create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_uuid', 191)->unique();
            $table->string('event_name', 100);
            $table->string('event_category', 50);
            $table->timestamp('occurred_at');
            $table->string('app_source', 32);
            $table->string('analytics_session_id', 191)->nullable();
            $table->string('order_form_session_id', 191)->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('course_title_snapshot', 255)->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('campaign_code', 100)->nullable();
            $table->string('campaign_channel', 50)->nullable();
            $table->string('campaign_content_depth', 50)->nullable();
            $table->string('cta_type', 50)->nullable();
            $table->string('landing_target', 50)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->unsignedBigInteger('form_order_id')->nullable();
            $table->unsignedBigInteger('payment_order_id')->nullable();
            $table->string('ab_test_id', 100)->nullable();
            $table->string('ab_variant_id', 100)->nullable();
            $table->string('route_name', 150)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('referrer_domain', 255)->nullable();
            $table->string('device_type', 32)->nullable();
            $table->string('browser_family', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
