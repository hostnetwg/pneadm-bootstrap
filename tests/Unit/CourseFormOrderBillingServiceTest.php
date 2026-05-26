<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Services\CourseFormOrderBillingService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CourseFormOrderBillingServiceTest extends TestCase
{
    public function test_has_meaningful_invoice(): void
    {
        $this->assertFalse(CourseFormOrderBillingService::hasMeaningfulInvoice(null));
        $this->assertFalse(CourseFormOrderBillingService::hasMeaningfulInvoice(''));
        $this->assertFalse(CourseFormOrderBillingService::hasMeaningfulInvoice('0'));
        $this->assertTrue(CourseFormOrderBillingService::hasMeaningfulInvoice('FV/1/05/2026'));
    }

    public function test_new_order_form_url_with_variant(): void
    {
        Config::set('services.pnedu_frontend_url', 'https://pnedu.pl');
        $course = new Course;
        $course->id = 510;

        $this->assertSame(
            'https://pnedu.pl/courses/510/order-form?price_variant_id=61',
            CourseFormOrderBillingService::newOrderFormUrl($course, 61)
        );

        $this->assertSame(
            'https://pnedu.pl/courses/510/order-form',
            CourseFormOrderBillingService::newOrderFormUrl($course)
        );
    }
}
