<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use App\Models\OrderFulfillment;
use App\Models\OrderItem;
use App\Models\OrderItemRecipient;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductPrice;
use App\Models\Role;
use App\Models\User;
use App\Services\FormOrderAdminParticipantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductOrderAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_order_has_dedicated_fulfillment_panel(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['is_active' => true]);
        $order = $this->createProductOrder();

        $this->actingAs($admin)
            ->get(route('form-orders.show', $order->id))
            ->assertOk()
            ->assertSee('DOSTĘPY DO PRODUKTU')
            ->assertSee('Nadaj dostęp')
            ->assertDontSee('Dodaj uczestnika do PNEDU');
    }

    public function test_manual_fulfillment_uses_protected_pnedu_internal_endpoint(): void
    {
        config([
            'services.pnedu.internal_url' => 'http://pnedu.test',
            'services.pnedu.internal_api_token' => 'test-internal-token',
        ]);
        Http::fake([
            'http://pnedu.test/api/internal/form-orders/*/fulfill-product' => Http::response([
                'success' => true,
                'fulfilled' => 1,
                'skipped' => 0,
                'failed' => 0,
                'message' => 'Nadano dostęp: 1; wcześniej obsłużone: 0.',
            ]),
        ]);

        $admin = User::factory()->create(['is_active' => true]);
        $order = $this->createProductOrder();

        $this->actingAs($admin)
            ->post(route('form-orders.product.fulfill', $order->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->url()
            === "http://pnedu.test/api/internal/form-orders/{$order->id}/fulfill-product"
            && $request->hasHeader('Authorization', 'Bearer test-internal-token'));
    }

    public function test_single_recipient_fulfillment_sends_recipient_id(): void
    {
        config([
            'services.pnedu.internal_url' => 'http://pnedu.test',
            'services.pnedu.internal_api_token' => 'test-internal-token',
        ]);
        Http::fake([
            'http://pnedu.test/api/internal/form-orders/*/fulfill-product' => Http::response([
                'success' => true,
                'fulfilled' => 1,
                'skipped' => 0,
                'failed' => 0,
                'message' => 'Nadano dostęp: 1; wcześniej obsłużone: 0.',
            ]),
        ]);

        $admin = User::factory()->create(['is_active' => true]);
        $order = $this->createProductOrder();
        $recipient = $order->orderItems->first()->recipients->first();

        $this->actingAs($admin)
            ->postJson(route('form-orders.product.fulfill', $order->id), [
                'recipient_id' => $recipient->id,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        Http::assertSent(fn ($request) => $request->url()
            === "http://pnedu.test/api/internal/form-orders/{$order->id}/fulfill-product"
            && (int) $request['recipient_id'] === (int) $recipient->id);
    }

    public function test_admin_can_revoke_product_access_and_keep_pnedu_account(): void
    {
        $order = $this->createProductOrder();
        $recipient = $order->orderItems->first()->recipients->first();
        $enrollment = $this->markRecipientFulfilled($order, $recipient, 'anna@example.test');

        $this->actingAs($this->admin())
            ->postJson(route('form-orders.product.revoke', $order->id), [
                'recipient_id' => $recipient->id,
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'revoked' => 1]);

        $this->assertDatabaseMissing('online_course_enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseHas('order_fulfillments', [
            'order_item_recipient_id' => $recipient->id,
            'status' => OrderFulfillment::STATUS_PENDING,
        ]);
        $this->assertNull($order->fresh()->pnedu_provisioned_at);
        $this->assertSame(OrderItemRecipient::STATUS_PENDING, $recipient->fresh()->status);
    }

    public function test_non_admin_cannot_revoke_product_access(): void
    {
        $order = $this->createProductOrder();
        $recipient = $order->orderItems->first()->recipients->first();
        $this->markRecipientFulfilled($order, $recipient, 'anna@example.test');

        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->postJson(route('form-orders.product.revoke', $order->id), [
                'recipient_id' => $recipient->id,
            ])
            ->assertForbidden();
    }

    public function test_product_order_show_lists_per_recipient_and_bulk_actions(): void
    {
        $this->withoutVite();
        $order = $this->createProductOrder(3);
        $first = $order->orderItems->first()->recipients->first();
        $this->markRecipientFulfilled($order, $first, $first->email);

        $this->actingAs($this->admin())
            ->get(route('form-orders.show', $order->id))
            ->assertOk()
            ->assertSee('Nadaj dostęp')
            ->assertSee('Nadaj dostęp wszystkim')
            ->assertSee('Wycofaj dostęp');
    }

    public function test_admin_participant_email_fix_updates_recipient_and_revokes_old_access(): void
    {
        $order = $this->createProductOrder();
        $recipient = $order->orderItems->first()->recipients->first();
        $enrollment = $this->markRecipientFulfilled($order, $recipient, 'anna@example.test');

        app(FormOrderAdminParticipantService::class)->sync($order, [
            ['first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => 'anna.poprawiona@example.test'],
        ]);

        $this->assertDatabaseMissing('online_course_enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseHas('order_item_recipients', [
            'id' => $recipient->id,
            'email' => 'anna.poprawiona@example.test',
            'status' => OrderItemRecipient::STATUS_PENDING,
        ]);
        $this->assertNull($order->fresh()->pnedu_provisioned_at);
    }

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin', 'level' => 50, 'is_system' => true]
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);
    }

    private function markRecipientFulfilled(FormOrder $order, OrderItemRecipient $recipient, string $email): OnlineCourseEnrollment
    {
        $courseId = (int) ($order->orderItems->first()->metadata['online_course_id'] ?? 0);
        $enrollment = OnlineCourseEnrollment::query()->create([
            'online_course_id' => $courseId,
            'email' => $email,
            'first_name' => $recipient->first_name,
            'last_name' => $recipient->last_name,
            'access_source' => 'product_order',
        ]);
        OrderFulfillment::query()->create([
            'order_item_recipient_id' => $recipient->id,
            'type' => OrderFulfillment::TYPE_ONLINE_COURSE_ACCESS,
            'idempotency_key' => "order-item-recipient:{$recipient->id}:online-course-access",
            'status' => OrderFulfillment::STATUS_SUCCEEDED,
            'online_course_enrollment_id' => $enrollment->id,
            'granted_at' => now('UTC'),
            'notified_at' => now('UTC'),
        ]);
        $recipient->update(['status' => OrderItemRecipient::STATUS_FULFILLED]);
        $order->update(['pnedu_provisioned_at' => now('UTC')]);

        return $enrollment;
    }

    private function createProductOrder(int $recipientCount = 1): FormOrder
    {
        $course = OnlineCourse::query()->create([
            'slug' => 'admin-product-order-test',
            'title' => 'Kurs produktowy w panelu',
            'is_active' => true,
            'visible_in_dashboard' => true,
        ]);
        $product = Product::query()->create([
            'type' => Product::TYPE_ONLINE_COURSE,
            'resource_id' => $course->id,
            'name' => $course->title,
            'slug' => 'admin-product-order-test',
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
        $order = FormOrder::query()->create([
            'ident' => FormOrder::generateIdent(),
            'order_date' => now('UTC'),
            'order_kind' => 'product',
            'product_name' => $product->name,
            'product_price' => '199.00',
            'orderer_name' => 'Szkoła Testowa',
            'orderer_email' => 'sekretariat@example.test',
            'buyer_name' => 'Gmina Testowa',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'status_completed' => 0,
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
            'quantity' => $recipientCount,
            'line_total' => number_format(199 * $recipientCount, 2, '.', ''),
            'currency' => 'PLN',
            'tax_treatment' => ProductPrice::TAX_EXEMPT,
            'access_policy' => ProductPrice::ACCESS_DURATION_FROM_GRANT,
            'access_duration_value' => 12,
            'access_duration_unit' => 'months',
            'metadata' => ['online_course_id' => $course->id],
        ]);

        for ($index = 1; $index <= $recipientCount; $index++) {
            $participant = FormOrderParticipant::query()->create([
                'form_order_id' => $order->id,
                'participant_firstname' => $index === 1 ? 'Anna' : 'Jan',
                'participant_lastname' => $index === 1 ? 'Nowak' : 'Kowalski',
                'participant_email' => $index === 1 ? 'anna@example.test' : "jan{$index}@example.test",
                'is_primary' => $index === 1,
            ]);
            OrderItemRecipient::query()->create([
                'order_item_id' => $item->id,
                'form_order_participant_id' => $participant->id,
                'first_name' => $participant->participant_firstname,
                'last_name' => $participant->participant_lastname,
                'email' => $participant->participant_email,
                'status' => OrderItemRecipient::STATUS_PENDING,
            ]);
        }

        return $order->fresh(['orderItems.recipients.fulfillments']);
    }
}
