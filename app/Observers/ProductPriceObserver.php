<?php

namespace App\Observers;

use App\Models\ProductPrice;
use App\Services\PriceOmnibusService;

class ProductPriceObserver
{
    public function saved(ProductPrice $price): void
    {
        if (
            $price->wasRecentlyCreated
            || $price->wasChanged([
                'price',
                'is_active',
                'is_promotion',
                'promotion_price',
                'promotion_starts_at',
                'promotion_ends_at',
            ])
        ) {
            app(PriceOmnibusService::class)->sync($price);
        }
    }

    public function deleted(ProductPrice $price): void
    {
        app(PriceOmnibusService::class)->close($price);
    }

    public function restored(ProductPrice $price): void
    {
        app(PriceOmnibusService::class)->sync($price);
    }
}
