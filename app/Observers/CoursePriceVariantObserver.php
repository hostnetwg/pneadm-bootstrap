<?php

namespace App\Observers;

use App\Models\CoursePriceVariant;
use App\Services\PneduFrontendCacheInvalidationService;
use Illuminate\Support\Facades\DB;

class CoursePriceVariantObserver
{
    public function saved(CoursePriceVariant $variant): void
    {
        $this->invalidatePneduUpcomingCoursesCache();
    }

    public function deleted(CoursePriceVariant $variant): void
    {
        $this->invalidatePneduUpcomingCoursesCache();
    }

    public function restored(CoursePriceVariant $variant): void
    {
        $this->invalidatePneduUpcomingCoursesCache();
    }

    private function invalidatePneduUpcomingCoursesCache(): void
    {
        DB::afterCommit(function () {
            app(PneduFrontendCacheInvalidationService::class)->invalidateUpcomingCourses();
        });
    }
}
