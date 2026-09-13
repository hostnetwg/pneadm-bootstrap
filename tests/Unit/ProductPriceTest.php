<?php

namespace Tests\Unit;

use App\Models\ProductPrice;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ProductPriceTest extends TestCase
{
    public function test_duration_access_is_calculated_from_grant_time(): void
    {
        $price = new ProductPrice([
            'access_policy' => ProductPrice::ACCESS_DURATION_FROM_GRANT,
            'access_duration_value' => 1,
            'access_duration_unit' => 'years',
        ]);

        $expiresAt = $price->accessExpiresAtFromGrant(
            CarbonImmutable::parse('2026-09-11 19:00:00', 'UTC')
        );

        $this->assertSame('2027-09-11 19:00:00', $expiresAt?->format('Y-m-d H:i:s'));
    }

    public function test_unlimited_access_has_no_expiration(): void
    {
        $price = new ProductPrice([
            'access_policy' => ProductPrice::ACCESS_UNLIMITED,
        ]);

        $this->assertNull(
            $price->accessExpiresAtFromGrant(CarbonImmutable::now('UTC'))
        );
    }

    public function test_promotion_price_is_used_only_inside_configured_window(): void
    {
        $price = new ProductPrice([
            'price' => 399,
            'is_promotion' => true,
            'promotion_price' => 299,
            'promotion_starts_at' => CarbonImmutable::parse('2026-09-01 00:00:00', 'UTC'),
            'promotion_ends_at' => CarbonImmutable::parse('2026-09-30 23:59:59', 'UTC'),
        ]);

        $this->assertSame('399.00', $price->currentPrice(CarbonImmutable::parse('2026-08-31 12:00:00', 'UTC')));
        $this->assertSame('299.00', $price->currentPrice(CarbonImmutable::parse('2026-09-15 12:00:00', 'UTC')));
        $this->assertSame('399.00', $price->currentPrice(CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC')));
    }

    public function test_promotion_countdown_requires_flag_end_date_and_active_window(): void
    {
        $price = new ProductPrice([
            'price' => 399,
            'is_promotion' => true,
            'promotion_price' => 299,
            'promotion_ends_at' => CarbonImmutable::parse('2026-09-30 23:59:59', 'UTC'),
            'show_promotion_countdown' => true,
        ]);
        $during = CarbonImmutable::parse('2026-09-15 12:00:00', 'UTC');

        $this->assertNotNull($price->promotionEndLabel($during));
        $this->assertTrue($price->shouldShowPromotionCountdown($during));
        $this->assertNotNull($price->promotionCountdownTargetIso());

        $price->show_promotion_countdown = false;
        $this->assertFalse($price->shouldShowPromotionCountdown($during));
    }
}
