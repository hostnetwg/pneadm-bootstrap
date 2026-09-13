<?php

namespace Tests\Unit;

use App\Models\PriceOfferHistory;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductPrice;
use App\Services\PriceOmnibusService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceOmnibusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lowest_price_uses_history_before_current_reduction_and_honors_exclusion(): void
    {
        $price = $this->makePrice('299.00', '149.00');
        $at = CarbonImmutable::parse('2026-09-13 12:00:00', 'UTC');

        PriceOfferHistory::query()
            ->where('subject_type', PriceOfferHistory::SUBJECT_PRODUCT_PRICE)
            ->where('subject_id', $price->id)
            ->delete();

        PriceOfferHistory::query()->create([
            'subject_type' => PriceOfferHistory::SUBJECT_PRODUCT_PRICE,
            'subject_id' => $price->id,
            'offered_price' => '199.00',
            'kind' => PriceOfferHistory::KIND_PROMOTIONAL,
            'effective_from' => $at->subDays(10),
            'effective_to' => $at->subDay(),
        ]);
        PriceOfferHistory::query()->create([
            'subject_type' => PriceOfferHistory::SUBJECT_PRODUCT_PRICE,
            'subject_id' => $price->id,
            'offered_price' => '149.00',
            'kind' => PriceOfferHistory::KIND_PROMOTIONAL,
            'effective_from' => $at->subDay(),
            'effective_to' => null,
        ]);

        $service = app(PriceOmnibusService::class);
        $this->assertSame('199.00', $service->lowestFor($price, $at));

        $mistake = PriceOfferHistory::query()
            ->where('offered_price', '199.00')
            ->where('subject_id', $price->id)
            ->firstOrFail();
        $service->exclude($mistake, 1, 'Testowa promocja');

        $this->assertSame('299.00', $service->lowestFor($price, $at));
    }

    public function test_deeper_discount_is_a_new_reduction_under_polish_rules(): void
    {
        $price = $this->makePrice('200.00', '120.00');
        $at = CarbonImmutable::parse('2026-09-13 12:00:00', 'UTC');

        PriceOfferHistory::query()
            ->where('subject_type', PriceOfferHistory::SUBJECT_PRODUCT_PRICE)
            ->where('subject_id', $price->id)
            ->delete();

        PriceOfferHistory::query()->create([
            'subject_type' => PriceOfferHistory::SUBJECT_PRODUCT_PRICE,
            'subject_id' => $price->id,
            'offered_price' => '200.00',
            'kind' => PriceOfferHistory::KIND_REGULAR,
            'effective_from' => $at->subDays(40),
            'effective_to' => $at->subDays(10),
        ]);
        PriceOfferHistory::query()->create([
            'subject_type' => PriceOfferHistory::SUBJECT_PRODUCT_PRICE,
            'subject_id' => $price->id,
            'offered_price' => '150.00',
            'kind' => PriceOfferHistory::KIND_PROMOTIONAL,
            'effective_from' => $at->subDays(10),
            'effective_to' => $at,
        ]);
        PriceOfferHistory::query()->create([
            'subject_type' => PriceOfferHistory::SUBJECT_PRODUCT_PRICE,
            'subject_id' => $price->id,
            'offered_price' => '120.00',
            'kind' => PriceOfferHistory::KIND_PROMOTIONAL,
            'effective_from' => $at,
            'effective_to' => null,
        ]);

        $this->assertSame('150.00', app(PriceOmnibusService::class)->lowestFor($price, $at));
    }

    public function test_sync_opens_a_new_segment_when_offered_price_changes(): void
    {
        $price = $this->makePrice('199.00');

        $open = PriceOfferHistory::query()
            ->where('subject_type', PriceOfferHistory::SUBJECT_PRODUCT_PRICE)
            ->where('subject_id', $price->id)
            ->whereNull('effective_to')
            ->firstOrFail();

        $this->assertSame('199.00', number_format((float) $open->offered_price, 2, '.', ''));
        $this->assertSame(PriceOfferHistory::KIND_REGULAR, $open->kind);

        $price->forceFill([
            'is_promotion' => true,
            'promotion_price' => '149.00',
        ])->save();

        $price->refresh();
        $history = app(PriceOmnibusService::class)->history($price);

        $this->assertCount(2, $history);
        $this->assertSame('149.00', number_format((float) $history->first()->offered_price, 2, '.', ''));
        $this->assertSame(PriceOfferHistory::KIND_PROMOTIONAL, $history->first()->kind);
        $this->assertNull($history->first()->effective_to);
        $this->assertNotNull($history->last()->effective_to);
    }

    private function makePrice(string $regular, ?string $promo = null): ProductPrice
    {
        $product = Product::query()->forceCreate([
            'type' => Product::TYPE_ONLINE_COURSE,
            'name' => 'Kurs omnibus',
            'slug' => 'kurs-omnibus-'.uniqid(),
            'fulfillment_type' => Product::FULFILLMENT_ONLINE_COURSE_ACCESS,
            'is_active' => true,
        ]);

        $offer = ProductOffer::query()->forceCreate([
            'product_id' => $product->id,
            'code' => ProductOffer::DEFAULT_CODE,
            'sales_channel' => ProductOffer::CHANNEL_PNEDU,
            'is_active' => true,
            'is_public' => true,
            'allow_multiple_recipients' => true,
            'allow_deferred_invoice' => true,
            'allow_payu' => true,
            'allow_paynow' => true,
            'sort_order' => 1,
        ]);

        return ProductPrice::query()->forceCreate([
            'product_offer_id' => $offer->id,
            'name' => 'Wariant omnibus',
            'is_active' => true,
            'sort_order' => 1,
            'price' => $regular,
            'currency' => 'PLN',
            'tax_treatment' => ProductPrice::TAX_EXEMPT,
            'is_promotion' => $promo !== null,
            'promotion_price' => $promo,
            'access_policy' => ProductPrice::ACCESS_UNLIMITED,
        ]);
    }
}
