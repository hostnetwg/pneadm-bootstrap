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
use App\Services\Dashboard\DashboardOrdersDashboardService;
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
            ->assertDontSee('Dodaj uczestnika do PNEDU')
            ->assertSee('„KURS:”', false)
            ->assertDontSee('„SZKOLENIE:”', false)
            ->assertDontSee('id="sendOnlinePaymentRecoveryBtn"', false);
    }

    public function test_unpaid_online_product_order_shows_recovery_email_button(): void
    {
        $this->withoutVite();
        $order = $this->createProductOrder();
        $order->update([
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_CANCELLED,
        ]);

        $this->actingAs($this->admin())
            ->get(route('form-orders.show', $order->id))
            ->assertOk()
            ->assertSee('id="sendOnlinePaymentRecoveryBtn"', false);
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

    public function test_product_access_email_preview_proxies_to_pnedu(): void
    {
        config([
            'services.pnedu.internal_url' => 'http://pnedu.test',
            'services.pnedu.internal_api_token' => 'test-internal-token',
        ]);
        Http::fake([
            'http://pnedu.test/api/internal/form-orders/*/preview-product-access-email*' => Http::response([
                'success' => true,
                'to' => 'anna@example.test',
                'subject' => 'Dostęp do kursu online: Test',
                'body' => 'Treść',
                'body_html' => '<p>Treść</p>',
                'variant' => 'new_user',
                'variant_label' => 'E-mail z linkiem do ustawienia hasła (nowe konto)',
                'mode' => 'grant',
            ]),
        ]);

        $order = $this->createProductOrder();
        $recipient = $order->orderItems->first()->recipients->first();

        $this->actingAs($this->admin())
            ->getJson(route('form-orders.product.access-email-preview', [
                'id' => $order->id,
                'recipient_id' => $recipient->id,
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('to', 'anna@example.test');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'preview-product-access-email')
            && (int) $request['recipient_id'] === (int) $recipient->id);
    }

    public function test_product_access_email_resend_proxies_to_pnedu(): void
    {
        config([
            'services.pnedu.internal_url' => 'http://pnedu.test',
            'services.pnedu.internal_api_token' => 'test-internal-token',
        ]);
        Http::fake([
            'http://pnedu.test/api/internal/form-orders/*/resend-product-access-email' => Http::response([
                'success' => true,
                'message' => 'Wysłano ponownie e-mail z dostępem do kursu.',
            ]),
        ]);

        $order = $this->createProductOrder();
        $recipient = $order->orderItems->first()->recipients->first();

        $this->actingAs($this->admin())
            ->postJson(route('form-orders.product.resend-access-email', $order->id), [
                'recipient_id' => $recipient->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(fn ($request) => $request->url()
            === "http://pnedu.test/api/internal/form-orders/{$order->id}/resend-product-access-email"
            && (int) $request['recipient_id'] === (int) $recipient->id);
    }

    public function test_product_order_show_has_access_email_preview_modal(): void
    {
        $this->withoutVite();
        $order = $this->createProductOrder();

        $this->actingAs($this->admin())
            ->get(route('form-orders.show', $order->id))
            ->assertOk()
            ->assertSee('id="productAccessEmailModal"', false)
            ->assertSee('Kopiuj treść', false)
            ->assertSee('js-product-access-email-btn', false)
            ->assertSee("document.addEventListener('DOMContentLoaded', initProductAccessEmailModal)", false);
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

    public function test_product_order_edit_uses_catalog_product_select_instead_of_training_course(): void
    {
        $this->withoutVite();
        $order = $this->createProductOrder();
        $product = Product::query()->findOrFail($order->orderItems->first()->product_id);

        $this->actingAs($this->admin())
            ->get(route('form-orders.edit', $order->id))
            ->assertOk()
            ->assertSee('Kurs online nagrany', false)
            ->assertSee('name="catalog_product_id"', false)
            ->assertSee('name="product_price_id"', false)
            ->assertSee('name="order_kind"', false)
            ->assertSee($product->name, false)
            ->assertSee('id="course_id"', false)
            ->assertDontSee('Wybierz element z listy', false);
    }

    public function test_admin_can_create_product_order_with_price_variant(): void
    {
        $catalog = $this->createCatalogProduct('Kurs z ADM create', 'kurs-z-adm-create', '175.00');
        $secondPrice = ProductPrice::query()->create([
            'product_offer_id' => $catalog['offer']->id,
            'name' => 'Dostęp 24 miesiące',
            'is_active' => true,
            'price' => '299.00',
            'currency' => 'PLN',
            'tax_treatment' => ProductPrice::TAX_EXEMPT,
            'access_policy' => ProductPrice::ACCESS_DURATION_FROM_GRANT,
            'access_duration_value' => 24,
            'access_duration_unit' => 'months',
        ]);

        $this->actingAs($this->admin())
            ->post(route('form-orders.store'), [
                'order_kind' => 'product',
                'catalog_product_id' => $catalog['product']->id,
                'product_price_id' => $secondPrice->id,
                'product_price' => '598.00',
                'orderer_name' => 'Szkoła ADM',
                'orderer_phone' => '501654274',
                'orderer_email' => 'adm@example.test',
                'buyer_name' => 'Gmina ADM',
                'buyer_address' => 'Testowa 1',
                'buyer_postal_code' => '00-001',
                'buyer_city' => 'Warszawa',
                'participants' => [
                    ['first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => 'anna@example.test'],
                    ['first_name' => 'Jan', 'last_name' => 'Kowalski', 'email' => 'jan@example.test'],
                ],
            ])
            ->assertRedirect();

        $order = FormOrder::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertTrue($order->isProductOrder());
        $this->assertNull($order->product_id);
        $this->assertSame('Kurs z ADM create', $order->product_name);
        $this->assertSame(FormOrder::SUBMISSION_SOURCE_PNEADM_MANUAL, $order->submission_source);
        $item = $order->orderItems()->first();
        $this->assertNotNull($item);
        $this->assertSame((int) $catalog['product']->id, (int) $item->product_id);
        $this->assertSame((int) $secondPrice->id, (int) $item->product_price_id);
        $this->assertSame((int) $catalog['course']->id, (int) ($item->metadata['online_course_id'] ?? 0));
        $this->assertSame(2, (int) $item->quantity);
        $this->assertCount(2, $item->recipients);
        $this->assertEquals(598.0, (float) $order->product_price);
    }

    public function test_clone_from_training_allows_product_kind_and_clone_from_product_prefills_catalog(): void
    {
        $this->withoutVite();
        $productOrder = $this->createProductOrder();
        $product = Product::query()->findOrFail($productOrder->orderItems->first()->product_id);

        $this->actingAs($this->admin())
            ->get(route('form-orders.create', ['clone_from' => $productOrder->id]))
            ->assertOk()
            ->assertSee('value="product"', false)
            ->assertSee('name="catalog_product_id"', false)
            ->assertSee($product->name, false)
            ->assertSee('anna@example.test', false);
    }

    public function test_admin_can_switch_training_order_to_product_on_edit(): void
    {
        $courseId = (int) \Illuminate\Support\Facades\DB::table('courses')->insertGetId([
            'title' => 'Szkolenie do zamiany',
            'description' => 'Test',
            'start_date' => now()->addDays(3),
            'end_date' => now()->addDays(4),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $order = FormOrder::query()->create([
            'ident' => FormOrder::generateIdent(),
            'order_date' => now('UTC'),
            'order_kind' => 'training',
            'product_id' => $courseId,
            'product_name' => 'Szkolenie do zamiany',
            'product_price' => '100.00',
            'orderer_name' => 'Szkoła',
            'orderer_email' => 'szkola@example.test',
            'orderer_phone' => '501654274',
            'buyer_name' => 'Gmina',
            'buyer_address' => 'Ul. 1',
            'buyer_postal_code' => '00-001',
            'buyer_city' => 'Warszawa',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        FormOrderParticipant::query()->create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Anna',
            'participant_lastname' => 'Nowak',
            'participant_email' => 'anna@example.test',
            'is_primary' => true,
        ]);
        $catalog = $this->createCatalogProduct('Kurs po zamianie', 'kurs-po-zamianie', '210.00');

        $this->actingAs($this->admin())
            ->put(route('form-orders.update', $order->id), [
                'from_edit_page' => '1',
                'order_kind' => 'product',
                'catalog_product_id' => $catalog['product']->id,
                'product_price_id' => $catalog['price']->id,
                'product_price' => '210.00',
                'orderer_name' => $order->orderer_name,
                'orderer_phone' => $order->orderer_phone,
                'orderer_email' => $order->orderer_email,
                'buyer_name' => $order->buyer_name,
                'buyer_address' => $order->buyer_address,
                'buyer_postal_code' => $order->buyer_postal_code,
                'buyer_city' => $order->buyer_city,
                'participants' => [[
                    'first_name' => 'Anna',
                    'last_name' => 'Nowak',
                    'email' => 'anna@example.test',
                ]],
            ])
            ->assertRedirect(route('form-orders.show', $order->id));

        $fresh = $order->fresh(['orderItems.recipients']);
        $this->assertTrue($fresh->isProductOrder());
        $this->assertNull($fresh->product_id);
        $this->assertSame('Kurs po zamianie', $fresh->product_name);
        $this->assertSame((int) $catalog['product']->id, (int) $fresh->orderItems->first()->product_id);
        $this->assertSame((int) $catalog['price']->id, (int) $fresh->orderItems->first()->product_price_id);
        $this->assertCount(1, $fresh->orderItems->first()->recipients);
    }

    public function test_product_order_update_saves_without_training_course_id(): void
    {
        $order = $this->createProductOrder();
        $productId = (int) $order->orderItems->first()->product_id;

        $this->actingAs($this->admin())
            ->put(route('form-orders.update', $order->id), $this->productEditPayload($order, [
                'orderer_name' => 'Szkoła Poprawiona',
            ]))
            ->assertRedirect(route('form-orders.show', $order->id))
            ->assertSessionHas('success');

        $this->assertSame('Szkoła Poprawiona', $order->fresh()->orderer_name);
        $this->assertNull($order->fresh()->product_id);
        $this->assertSame($productId, (int) $order->fresh()->orderItems()->first()->product_id);
    }

    public function test_product_order_update_can_switch_catalog_product(): void
    {
        $order = $this->createProductOrder();
        $other = $this->createCatalogProduct('Inny kurs nagrany', 'inny-kurs-nagrany', '249.00');

        $this->actingAs($this->admin())
            ->put(route('form-orders.update', $order->id), $this->productEditPayload($order, [
                'catalog_product_id' => $other['product']->id,
            ]))
            ->assertRedirect(route('form-orders.show', $order->id));

        $fresh = $order->fresh(['orderItems']);
        $this->assertNull($fresh->product_id);
        $this->assertSame('Inny kurs nagrany', $fresh->product_name);
        $this->assertSame((int) $other['product']->id, (int) $fresh->orderItems->first()->product_id);
        $this->assertSame((int) $other['course']->id, (int) ($fresh->orderItems->first()->metadata['online_course_id'] ?? 0));
        $this->assertEquals(249.0, (float) $fresh->product_price);
    }

    public function test_dashboard_recent_orders_show_catalog_product_name_under_produkt_column(): void
    {
        $this->withoutVite();
        $order = $this->createProductOrder();

        $row = app(DashboardOrdersDashboardService::class)->mapRecentOrderRow($order);
        $this->assertSame('Kurs produktowy w panelu', $row['course_title']);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('>Produkt</th>', false)
            ->assertSee($order->ident, false)
            ->assertSee('Kurs produktowy w panelu', false);
    }

    public function test_catalog_product_search_returns_online_courses(): void
    {
        $order = $this->createProductOrder();
        $product = Product::query()->findOrFail($order->orderItems->first()->product_id);

        $this->actingAs($this->admin())
            ->getJson(route('form-orders.products.search', ['q' => 'produktowy']))
            ->assertOk()
            ->assertJsonPath('items.0.id', $product->id)
            ->assertJsonPath('items.0.title_text', $product->name);
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productEditPayload(FormOrder $order, array $overrides = []): array
    {
        $item = $order->orderItems->first();
        $participant = $order->participants()->first();

        return array_merge([
            'from_edit_page' => '1',
            'order_kind' => 'product',
            'catalog_product_id' => $item?->product_id,
            'product_price_id' => $item?->product_price_id,
            'product_price' => $order->product_price,
            'orderer_name' => $order->orderer_name,
            'orderer_email' => $order->orderer_email,
            'buyer_name' => $order->buyer_name,
            'participants' => [[
                'first_name' => $participant?->participant_firstname ?? 'Anna',
                'last_name' => $participant?->participant_lastname ?? 'Nowak',
                'email' => $participant?->participant_email ?? 'anna@example.test',
            ]],
        ], $overrides);
    }

    /**
     * @return array{course: OnlineCourse, product: Product, offer: ProductOffer, price: ProductPrice}
     */
    private function createCatalogProduct(string $title, string $slug, string $amount = '199.00'): array
    {
        $course = OnlineCourse::query()->create([
            'slug' => $slug,
            'title' => $title,
            'is_active' => true,
            'visible_in_dashboard' => true,
        ]);
        $product = Product::query()->create([
            'type' => Product::TYPE_ONLINE_COURSE,
            'resource_id' => $course->id,
            'name' => $title,
            'slug' => $slug,
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
            'price' => $amount,
            'currency' => 'PLN',
            'tax_treatment' => ProductPrice::TAX_EXEMPT,
            'access_policy' => ProductPrice::ACCESS_DURATION_FROM_GRANT,
            'access_duration_value' => 12,
            'access_duration_unit' => 'months',
        ]);

        return compact('course', 'product', 'offer', 'price');
    }

    private function createProductOrder(int $recipientCount = 1): FormOrder
    {
        $catalog = $this->createCatalogProduct('Kurs produktowy w panelu', 'admin-product-order-test');
        $product = $catalog['product'];
        $offer = $catalog['offer'];
        $price = $catalog['price'];
        $course = $catalog['course'];
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
