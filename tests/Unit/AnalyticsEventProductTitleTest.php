<?php

namespace Tests\Unit;

use App\Models\Analytics\AnalyticsEvent;
use Tests\TestCase;

class AnalyticsEventProductTitleTest extends TestCase
{
    public function test_product_slug_is_read_from_storefront_paths(): void
    {
        $this->assertSame(
            'tik-w-pracy-nauczyciela',
            AnalyticsEvent::productSlugFromPath('/kursy/tik-w-pracy-nauczyciela')
        );
        $this->assertSame(
            'tik-w-pracy-nauczyciela',
            AnalyticsEvent::productSlugFromPath('/kursy/tik-w-pracy-nauczyciela/zamowienie?price=13')
        );
        $this->assertNull(AnalyticsEvent::productSlugFromPath('/courses/560/order-form'));
        $this->assertNull(AnalyticsEvent::productSlugFromPath('/kursy'));
    }
}
