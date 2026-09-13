<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\OnlineCourse;
use App\Models\OrderFulfillment;
use App\Models\OrderItem;
use App\Models\OrderItemRecipient;
use App\Models\Participant;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\FormOrderOperationalStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormOrderOperationalStatusTest extends TestCase
{
    use RefreshDatabase;

    private function createCourse(array $overrides = []): int
    {
        return (int) DB::table('courses')->insertGetId(array_merge([
            'title' => 'Kurs testowy',
            'description' => 'Test',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createOrderWithParticipant(int $courseId, array $orderOverrides = [], array $participantOverrides = []): FormOrder
    {
        $order = FormOrder::create(array_merge([
            'product_id' => $courseId,
            'product_name' => 'Szkolenie test',
            'invoice_number' => null,
            'status_completed' => 0,
            'orderer_email' => 'orderer@example.test',
        ], $orderOverrides));

        FormOrderParticipant::create(array_merge([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Jan',
            'participant_lastname' => 'Kowalski',
            'participant_email' => 'jan@example.test',
            'is_primary' => true,
        ], $participantOverrides));

        return $order->fresh(['participants']);
    }

    public function test_order_without_invoice_and_without_participant_needs_attention(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId);

        $service = app(FormOrderOperationalStatusService::class);

        $this->assertTrue($service->needsAttention($order));
        $this->assertSame(FormOrderOperationalStatusService::STATUS_NEEDS_PROVISIONING, $service->evaluate($order)['status']);
        $this->assertTrue(FormOrder::new()->whereKey($order->id)->exists());
    }

    public function test_order_with_invoice_but_without_participant_still_needs_attention(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId, ['invoice_number' => 'FV/1/2026']);

        $service = app(FormOrderOperationalStatusService::class);

        $this->assertTrue($service->needsAttention($order));
        $this->assertContains($service->evaluate($order)['status'], [
            FormOrderOperationalStatusService::STATUS_NEEDS_PROVISIONING,
            FormOrderOperationalStatusService::STATUS_INCONSISTENT,
        ]);
    }

    public function test_order_without_invoice_with_participant_needs_invoice(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId);

        $participant = Participant::create([
            'course_id' => $courseId,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.test',
            'order' => 1,
        ]);

        FormOrderParticipant::where('form_order_id', $order->id)->update(['participant_id' => $participant->id]);
        $order = $order->fresh(['participants']);

        $service = app(FormOrderOperationalStatusService::class);

        $this->assertFalse($service->needsAttention($order));
        $this->assertTrue($service->needsOperationalHandling($order));
        $this->assertFalse($service->isProcessed($order));
        $this->assertSame(FormOrderOperationalStatusService::STATUS_NEEDS_INVOICE, $service->evaluate($order)['status']);
        $this->assertTrue(FormOrder::needsHandling()->whereKey($order->id)->exists());
        $this->assertFalse(FormOrder::processed()->whereKey($order->id)->exists());
    }

    public function test_order_with_invoice_and_participant_is_processed(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId, ['invoice_number' => 'FV/1/2026']);

        $participant = Participant::create([
            'course_id' => $courseId,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.test',
            'order' => 1,
        ]);

        FormOrderParticipant::where('form_order_id', $order->id)->update(['participant_id' => $participant->id]);
        $order = $order->fresh(['participants']);

        $service = app(FormOrderOperationalStatusService::class);

        $this->assertFalse($service->needsAttention($order));
        $this->assertFalse($service->needsOperationalHandling($order));
        $this->assertTrue($service->isProcessed($order));
        $this->assertSame(FormOrderOperationalStatusService::STATUS_PROCESSED, $service->evaluate($order)['status']);
        $this->assertFalse(FormOrder::needsHandling()->whereKey($order->id)->exists());
        $this->assertTrue(FormOrder::processed()->whereKey($order->id)->exists());
    }

    public function test_order_with_participant_and_invoice_exempt_is_processed_without_invoice(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId, [
            'invoice_exempt_at' => now(),
            'invoice_exempt_reason' => 'Bezpłatny dostęp',
        ]);

        $participant = Participant::create([
            'course_id' => $courseId,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.test',
            'order' => 1,
        ]);

        FormOrderParticipant::where('form_order_id', $order->id)->update(['participant_id' => $participant->id]);
        $order = $order->fresh(['participants']);

        $service = app(FormOrderOperationalStatusService::class);

        $this->assertFalse($service->needsOperationalHandling($order));
        $this->assertTrue($service->isProcessed($order));
        $this->assertSame(FormOrderOperationalStatusService::STATUS_PROCESSED, $service->evaluate($order)['status']);
        $this->assertFalse(FormOrder::needsHandling()->whereKey($order->id)->exists());
        $this->assertTrue(FormOrder::processed()->whereKey($order->id)->exists());
    }

    public function test_course_badge_counts_split_missing_participants_from_missing_invoices(): void
    {
        $courseId = $this->createCourse();
        $service = app(FormOrderOperationalStatusService::class);

        $missingBoth = $this->createOrderWithParticipant($courseId, [], [
            'participant_email' => 'missing-both@example.test',
        ]);

        $missingInvoice = $this->createOrderWithParticipant($courseId, [], [
            'participant_email' => 'missing-invoice@example.test',
        ]);
        $missingInvoiceParticipant = Participant::create([
            'course_id' => $courseId,
            'first_name' => 'Anna',
            'last_name' => 'Faktura',
            'email' => 'missing-invoice@example.test',
            'order' => 1,
        ]);
        FormOrderParticipant::where('form_order_id', $missingInvoice->id)
            ->update(['participant_id' => $missingInvoiceParticipant->id]);
        $missingInvoice->update(['pnedu_provisioned_at' => now()]);

        $missingParticipant = $this->createOrderWithParticipant($courseId, ['invoice_number' => 'FV/1/2026'], [
            'participant_email' => 'missing-participant@example.test',
        ]);

        $freeAccessDone = $this->createOrderWithParticipant($courseId, [
            'invoice_exempt_at' => now(),
            'invoice_exempt_reason' => 'Bezpłatny dostęp',
        ], [
            'participant_email' => 'free-access@example.test',
        ]);
        $freeAccessParticipant = Participant::create([
            'course_id' => $courseId,
            'first_name' => 'Darmowy',
            'last_name' => 'Dostep',
            'email' => 'free-access@example.test',
            'order' => 1,
        ]);
        FormOrderParticipant::where('form_order_id', $freeAccessDone->id)
            ->update(['participant_id' => $freeAccessParticipant->id]);
        $freeAccessDone->update(['pnedu_provisioned_at' => now()]);

        $cancelled = $this->createOrderWithParticipant($courseId, [
            'cancelled_at' => now(),
            'cancelled_reason' => 'duplikat',
        ], [
            'participant_email' => 'cancelled@example.test',
        ]);

        $participantsCounts = $service->countNeedsProvisioningByCourseIds([$courseId]);
        $latestNeedsProvisioningId = $service->latestNeedsProvisioningOrderIdByCourseIds([$courseId]);
        $invoiceCounts = $service->countNeedsInvoiceByCourseIds([$courseId]);

        $this->assertSame(2, $participantsCounts[$courseId] ?? 0);
        $this->assertSame(
            max($missingBoth->id, $missingParticipant->id),
            $latestNeedsProvisioningId[$courseId] ?? null
        );
        $this->assertSame(2, $invoiceCounts[$courseId] ?? 0);

        $this->assertTrue(FormOrder::new()->whereKey($missingBoth->id)->exists());
        $this->assertTrue(FormOrder::needsInvoice()->whereKey($missingBoth->id)->exists());
        $this->assertFalse(FormOrder::new()->whereKey($missingInvoice->id)->exists());
        $this->assertTrue(FormOrder::needsInvoice()->whereKey($missingInvoice->id)->exists());
        $this->assertTrue(FormOrder::new()->whereKey($missingParticipant->id)->exists());
        $this->assertFalse(FormOrder::needsInvoice()->whereKey($missingParticipant->id)->exists());
        $this->assertFalse(FormOrder::new()->whereKey($freeAccessDone->id)->exists());
        $this->assertFalse(FormOrder::needsInvoice()->whereKey($freeAccessDone->id)->exists());
        $this->assertFalse(FormOrder::new()->whereKey($cancelled->id)->exists());
        $this->assertFalse(FormOrder::needsInvoice()->whereKey($cancelled->id)->exists());
    }

    public function test_needs_invoice_stats_include_ifirma_id_without_invoice_number(): void
    {
        $courseId = $this->createCourse();
        $service = app(FormOrderOperationalStatusService::class);

        $this->createOrderWithParticipant($courseId, [
            'invoice_number' => null,
            'ifirma_invoice_id' => null,
        ], [
            'participant_email' => 'no-invoice@example.test',
        ]);

        $ifirmaOnly = $this->createOrderWithParticipant($courseId, [
            'invoice_number' => null,
            'ifirma_invoice_id' => '131625449',
        ], [
            'participant_email' => 'ifirma-only@example.test',
        ]);

        $stats = $service->needsInvoiceStatsByCourseIds([$courseId]);

        $this->assertSame(2, $stats['counts'][$courseId] ?? 0);
        $this->assertTrue(FormOrder::needsInvoice()->whereKey($ifirmaOnly->id)->exists());
    }

    public function test_status_completed_without_participant_still_needs_attention(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId, ['status_completed' => 1]);

        $this->assertTrue(app(FormOrderOperationalStatusService::class)->needsAttention($order->fresh(['participants'])));
    }

    public function test_cancelled_order_is_not_in_new_scope(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId, [
            'cancelled_at' => now(),
            'cancelled_reason' => 'test',
        ]);

        $this->assertFalse(FormOrder::new()->whereKey($order->id)->exists());
    }

    public function test_partially_provisioned_multi_participant_order_needs_attention(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId);

        FormOrderParticipant::create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Anna',
            'participant_lastname' => 'Nowak',
            'participant_email' => 'anna@example.test',
            'is_primary' => false,
        ]);

        Participant::create([
            'course_id' => $courseId,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.test',
            'order' => 1,
        ]);

        $order = $order->fresh(['participants']);
        $status = app(FormOrderOperationalStatusService::class)->evaluate($order);

        $this->assertSame(FormOrderOperationalStatusService::STATUS_PARTIALLY_PROCESSED, $status['status']);
        $this->assertSame(2, $status['expected_count']);
        $this->assertSame(1, $status['provisioned_count']);
        $this->assertTrue(FormOrder::new()->whereKey($order->id)->exists());
    }

    public function test_invoice_line_quantity_uses_participant_count(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId, ['product_price' => 600]);

        FormOrderParticipant::create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Anna',
            'participant_lastname' => 'Nowak',
            'participant_email' => 'anna@example.test',
            'is_primary' => false,
        ]);

        $order = $order->fresh(['participants']);
        $this->assertSame(2, $order->invoiceLineQuantity());
        $this->assertSame(300.0, $order->invoiceUnitPrice());
    }

    public function test_cancel_order_endpoint_sets_cancelled_at(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId);

        $response = $this->actingAs($user)->postJson(route('form-orders.cancel', $order->id), [
            'reason' => 'rezygnacja',
        ]);

        $response->assertOk();
        $order->refresh();
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame('rezygnacja', $order->cancelled_reason);
    }

    public function test_operational_status_partial_updates_after_invoice(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId);

        $participant = Participant::create([
            'course_id' => $courseId,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.test',
            'order' => 1,
        ]);

        FormOrderParticipant::where('form_order_id', $order->id)->update(['participant_id' => $participant->id]);

        $before = $this->actingAs($user)->get(route('form-orders.operational-status', $order->id));
        $before->assertOk();
        $before->assertSee('STATUS ZAMÓWIENIA', false);
        $before->assertSee('Nieprzetworzone', false);
        $before->assertSee('Uczestnik dodany do szkolenia, ale faktura nie została wystawiona.', false);
        $before->assertSee('Do wystawienia FV', false);
        $before->assertSee('Faktura nie wystawiona', false);

        $order->update(['invoice_number' => 'FV/99/2026']);

        $after = $this->actingAs($user)->get(route('form-orders.operational-status', $order->id));
        $after->assertOk();
        $after->assertDontSee('Uczestnik dodany do szkolenia, ale faktura nie została wystawiona.', false);
        $after->assertDontSee('Nieprzetworzone', false);
        $after->assertSee('Przetworzone', false);
        $after->assertSee('Faktura wystawiona', false);
    }

    public function test_unfulfilled_product_order_needs_provisioning_and_stays_in_active_queue(): void
    {
        $order = $this->createProductOrder();
        $service = app(FormOrderOperationalStatusService::class);
        $status = $service->evaluate($order->fresh(['orderItems.recipients.fulfillments']));

        $this->assertSame(FormOrderOperationalStatusService::STATUS_NEEDS_PROVISIONING, $status['status']);
        $this->assertSame('Do nadania dostępu', $status['label']);
        $this->assertSame(1, $status['expected_count']);
        $this->assertSame(0, $status['provisioned_count']);
        $this->assertNull($status['course_id']);
        $this->assertTrue($service->needsAttention($order));
        $this->assertTrue($service->needsOperationalHandling($order));
        $this->assertTrue(FormOrder::new()->whereKey($order->id)->exists());
        $this->assertTrue(FormOrder::needsHandling()->whereKey($order->id)->exists());
        $this->assertTrue(FormOrder::needsActiveHandling()->whereKey($order->id)->exists());
        $this->assertFalse(FormOrder::processed()->whereKey($order->id)->exists());
    }

    public function test_fulfilled_product_order_without_invoice_needs_invoice_not_course_participant(): void
    {
        $order = $this->createProductOrder();
        $this->markProductOrderFulfilled($order);

        $service = app(FormOrderOperationalStatusService::class);
        $status = $service->evaluate($order->fresh(['orderItems.recipients.fulfillments']));

        $this->assertSame(FormOrderOperationalStatusService::STATUS_NEEDS_INVOICE, $status['status']);
        $this->assertSame(1, $status['provisioned_count']);
        $this->assertFalse($service->needsAttention($order->fresh()));
        $this->assertTrue($service->needsOperationalHandling($order->fresh()));
        $this->assertTrue(FormOrder::needsHandling()->whereKey($order->id)->exists());
        $this->assertTrue(FormOrder::needsActiveHandling()->whereKey($order->id)->exists());
        $this->assertFalse(FormOrder::new()->whereKey($order->id)->exists());
        $this->assertFalse(FormOrder::processed()->whereKey($order->id)->exists());
        $this->assertStringContainsString('Dostęp nadany, ale faktura nie została wystawiona.', implode(' ', $status['warnings']));
    }

    public function test_fulfilled_and_invoiced_product_order_is_processed(): void
    {
        $order = $this->createProductOrder(['invoice_number' => 'FV/KURS/1/2026']);
        $this->markProductOrderFulfilled($order);

        $service = app(FormOrderOperationalStatusService::class);
        $status = $service->evaluate($order->fresh(['orderItems.recipients.fulfillments']));

        $this->assertSame(FormOrderOperationalStatusService::STATUS_PROCESSED, $status['status']);
        $this->assertFalse($service->needsAttention($order->fresh()));
        $this->assertFalse($service->needsOperationalHandling($order->fresh()));
        $this->assertFalse(FormOrder::needsHandling()->whereKey($order->id)->exists());
        $this->assertFalse(FormOrder::needsActiveHandling()->whereKey($order->id)->exists());
        $this->assertTrue(FormOrder::processed()->whereKey($order->id)->exists());
    }

    /**
     * @param  array<string, mixed>  $orderOverrides
     */
    private function createProductOrder(array $orderOverrides = []): FormOrder
    {
        $course = OnlineCourse::query()->create([
            'slug' => 'status-product-'.uniqid(),
            'title' => 'Kurs produktowy status',
            'is_active' => true,
            'visible_in_dashboard' => true,
        ]);
        $product = Product::query()->create([
            'type' => Product::TYPE_ONLINE_COURSE,
            'resource_id' => $course->id,
            'name' => $course->title,
            'slug' => 'status-product-'.uniqid(),
            'fulfillment_type' => Product::FULFILLMENT_ONLINE_COURSE_ACCESS,
            'is_active' => true,
            'requires_shipping' => false,
        ]);
        $offer = ProductOffer::query()->create([
            'product_id' => $product->id,
            'code' => ProductOffer::DEFAULT_CODE,
            'sales_channel' => ProductOffer::CHANNEL_PNEDU,
            'is_active' => true,
            'is_public' => true,
            'allow_multiple_recipients' => true,
            'allow_deferred_invoice' => true,
        ]);
        $price = ProductPrice::query()->create([
            'product_offer_id' => $offer->id,
            'name' => 'Dostęp 12 miesięcy',
            'is_active' => true,
            'price' => '199.00',
            'currency' => 'PLN',
            'tax_treatment' => ProductPrice::TAX_EXEMPT,
            'access_policy' => ProductPrice::ACCESS_DURATION_FROM_GRANT,
            'access_duration_value' => 12,
            'access_duration_unit' => 'months',
        ]);
        $order = FormOrder::query()->create(array_merge([
            'ident' => FormOrder::generateIdent(),
            'order_date' => now('UTC'),
            'order_kind' => 'product',
            'product_name' => $product->name,
            'product_price' => '199.00',
            'orderer_name' => 'Szkoła Status',
            'orderer_email' => 'status-'.uniqid().'@example.test',
            'buyer_name' => 'Gmina Testowa',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'status_completed' => 0,
        ], $orderOverrides));
        $participant = FormOrderParticipant::query()->create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Anna',
            'participant_lastname' => 'Nowak',
            'participant_email' => 'anna-status@example.test',
            'is_primary' => true,
        ]);
        $item = OrderItem::query()->create([
            'form_order_id' => $order->id,
            'product_id' => $product->id,
            'product_offer_id' => $offer->id,
            'product_price_id' => $price->id,
            'product_type' => Product::TYPE_ONLINE_COURSE,
            'product_name' => $product->name,
            'fulfillment_type' => Product::FULFILLMENT_ONLINE_COURSE_ACCESS,
            'requires_shipping' => false,
            'unit_price' => '199.00',
            'quantity' => 1,
            'line_total' => '199.00',
            'currency' => 'PLN',
            'tax_treatment' => ProductPrice::TAX_EXEMPT,
            'access_policy' => ProductPrice::ACCESS_DURATION_FROM_GRANT,
            'access_duration_value' => 12,
            'access_duration_unit' => 'months',
            'metadata' => ['online_course_id' => $course->id],
        ]);
        OrderItemRecipient::query()->create([
            'order_item_id' => $item->id,
            'form_order_participant_id' => $participant->id,
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna-status@example.test',
            'status' => OrderItemRecipient::STATUS_PENDING,
        ]);

        return $order->fresh(['orderItems.recipients.fulfillments', 'participants']);
    }

    private function markProductOrderFulfilled(FormOrder $order): void
    {
        $recipient = $order->orderItems->firstOrFail()->recipients->firstOrFail();
        OrderFulfillment::query()->create([
            'order_item_recipient_id' => $recipient->id,
            'type' => OrderFulfillment::TYPE_ONLINE_COURSE_ACCESS,
            'idempotency_key' => 'test-product-status-'.$recipient->id,
            'status' => OrderFulfillment::STATUS_SUCCEEDED,
            'granted_at' => now('UTC'),
        ]);
        $recipient->update(['status' => OrderItemRecipient::STATUS_FULFILLED]);
        $order->update(['pnedu_provisioned_at' => now('UTC')]);
    }
}
