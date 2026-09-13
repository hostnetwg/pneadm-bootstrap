<?php

namespace App\Observers;

use App\Models\CoursePriceVariant;
use App\Services\PneduFrontendCacheInvalidationService;
use App\Services\PriceOmnibusService;
use Illuminate\Support\Facades\DB;

class CoursePriceVariantObserver
{
    public function saved(CoursePriceVariant $variant): void
    {
        if (
            $variant->wasRecentlyCreated
            || $variant->wasChanged([
                'price',
                'is_active',
                'is_promotion',
                'promotion_price',
                'promotion_type',
                'promotion_start',
                'promotion_end',
            ])
        ) {
            app(PriceOmnibusService::class)->sync($variant);
        }

        $this->invalidatePneduUpcomingCoursesCache();
    }

    public function deleted(CoursePriceVariant $variant): void
    {
        app(PriceOmnibusService::class)->close($variant);
        $this->invalidatePneduUpcomingCoursesCache();
    }

    public function restored(CoursePriceVariant $variant): void
    {
        app(PriceOmnibusService::class)->sync($variant);
        $this->invalidatePneduUpcomingCoursesCache();
    }

    private function invalidatePneduUpcomingCoursesCache(): void
    {
        DB::afterCommit(function () {
            app(PneduFrontendCacheInvalidationService::class)->invalidateUpcomingCourses();
        });
    }
}
