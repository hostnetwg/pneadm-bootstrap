<?php

namespace Tests\Feature;

use App\Models\OnlineCourse;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineCourseSalesCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_configures_online_course_sales_and_price_variants(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['is_active' => true]);
        $course = OnlineCourse::query()->create([
            'slug' => 'kurs-prawo-oswiatowe',
            'title' => 'Prawo oświatowe w praktyce',
            'is_active' => true,
            'visible_in_dashboard' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('online-courses.sales.update', $course), [
                'product_slug' => 'prawo-oswiatowe-online',
                'sku' => 'KO-PRAWO-2026',
                'meta_title' => 'Kurs prawa oświatowego online',
                'meta_description' => 'Nagrany kurs dla dyrektorów i nauczycieli.',
                'sales_enabled' => '1',
                'is_public' => '1',
                'allow_deferred_invoice' => '1',
                'allow_payu' => '1',
                'allow_paynow' => '1',
            ])
            ->assertRedirect(route('online-courses.sales.edit', $course))
            ->assertSessionHas('success');

        $product = Product::query()->where([
            'type' => Product::TYPE_ONLINE_COURSE,
            'resource_id' => $course->id,
        ])->firstOrFail();
        $this->assertSame('prawo-oswiatowe-online', $product->slug);
        $this->assertSame('KO-PRAWO-2026', $product->sku);
        $this->assertTrue($product->is_active);
        $this->assertFalse($product->requires_shipping);

        $offer = ProductOffer::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertTrue($offer->is_public);
        $this->assertTrue($offer->allow_multiple_recipients);
        $this->assertTrue($offer->allow_deferred_invoice);
        $this->assertTrue($offer->allow_payu);
        $this->assertTrue($offer->allow_paynow);
        $this->assertSame(30, $offer->satisfaction_guarantee_days);

        $this->actingAs($admin)
            ->post(route('online-courses.sales.prices.store', $course), [
                'name' => 'Dostęp na 12 miesięcy',
                'description' => 'Cena za jednego uczestnika.',
                'is_active' => '1',
                'sort_order' => '10',
                'price' => '349.00',
                'tax_treatment' => ProductPrice::TAX_EXEMPT,
                'tax_exemption_basis' => '',
                'is_promotion' => '0',
                'access_policy' => ProductPrice::ACCESS_DURATION_FROM_GRANT,
                'access_starts_at' => '2026-11-01T09:00',
                'access_note' => 'Materiały publikujemy partiami',
                'access_duration_value' => '1',
                'access_duration_unit' => 'years',
            ])
            ->assertRedirect(route('online-courses.sales.edit', $course))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('product_prices', [
            'product_offer_id' => $offer->id,
            'name' => 'Dostęp na 12 miesięcy',
            'price' => '349.00',
            'tax_treatment' => ProductPrice::TAX_EXEMPT,
            'tax_rate' => null,
            'access_policy' => ProductPrice::ACCESS_DURATION_FROM_GRANT,
            'access_duration_value' => 1,
            'access_duration_unit' => 'years',
            'access_note' => 'Materiały publikujemy partiami',
        ]);

        $createdPrice = ProductPrice::query()
            ->where('product_offer_id', $offer->id)
            ->where('name', 'Dostęp na 12 miesięcy')
            ->firstOrFail();
        $this->assertSame(
            '2026-11-01',
            $createdPrice->access_starts_at?->timezone('Europe/Warsaw')->format('Y-m-d')
        );

    }

    public function test_active_sales_requires_at_least_one_payment_method(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $course = OnlineCourse::query()->create([
            'slug' => 'kurs-bez-platnosci',
            'title' => 'Kurs bez płatności',
            'is_active' => true,
            'visible_in_dashboard' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('online-courses.sales.edit', $course))
            ->put(route('online-courses.sales.update', $course), [
                'product_slug' => 'kurs-bez-platnosci',
                'sales_enabled' => '1',
                'is_public' => '0',
                'allow_deferred_invoice' => '0',
                'allow_payu' => '0',
                'allow_paynow' => '0',
            ])
            ->assertRedirect(route('online-courses.sales.edit', $course))
            ->assertSessionHasErrors('allow_deferred_invoice');

        $this->assertDatabaseMissing('products', [
            'type' => Product::TYPE_ONLINE_COURSE,
            'resource_id' => $course->id,
        ]);
    }

    public function test_admin_can_enable_promotion_countdown_for_price_variant(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['is_active' => true]);
        $course = OnlineCourse::query()->create([
            'slug' => 'kurs-promocja-licznik',
            'title' => 'Kurs z promocją',
            'is_active' => true,
            'visible_in_dashboard' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('online-courses.sales.update', $course), [
                'product_slug' => 'kurs-promocja-licznik',
                'sales_enabled' => '1',
                'is_public' => '1',
                'allow_deferred_invoice' => '1',
                'allow_payu' => '1',
                'allow_paynow' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('online-courses.sales.prices.store', $course), [
                'name' => 'Dostęp promocyjny',
                'is_active' => '1',
                'sort_order' => '10',
                'price' => '199.00',
                'tax_treatment' => ProductPrice::TAX_EXEMPT,
                'is_promotion' => '1',
                'promotion_price' => '149.00',
                'promotion_starts_at' => '2026-09-01T00:00',
                'promotion_ends_at' => '2026-10-01T17:00',
                'show_promotion_countdown' => '1',
                'access_policy' => ProductPrice::ACCESS_UNLIMITED,
            ])
            ->assertRedirect(route('online-courses.sales.edit', $course));

        $price = ProductPrice::query()->where('name', 'Dostęp promocyjny')->firstOrFail();
        $this->assertTrue($price->is_promotion);
        $this->assertTrue($price->show_promotion_countdown);
        $this->assertNotNull($price->promotion_ends_at);
    }

    public function test_admin_can_keep_course_in_catalog_with_sales_disabled(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $course = OnlineCourse::query()->create([
            'slug' => 'kurs-archiwalny-canva',
            'title' => 'Canva — stara wersja',
            'is_active' => true,
            'visible_in_dashboard' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('online-courses.sales.update', $course), [
                'product_slug' => 'canva-stara-wersja',
                'sales_enabled' => '0',
                'is_public' => '1',
                'allow_deferred_invoice' => '1',
                'allow_payu' => '1',
                'allow_paynow' => '1',
            ])
            ->assertRedirect(route('online-courses.sales.edit', $course));

        $product = Product::query()->where([
            'type' => Product::TYPE_ONLINE_COURSE,
            'resource_id' => $course->id,
        ])->firstOrFail();
        $this->assertTrue($product->is_active);

        $offer = ProductOffer::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertFalse($offer->is_active);
        $this->assertTrue($offer->is_public);
    }
}
