<?php

namespace Tests\Unit;

use App\Models\PaymentDisplayOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentDisplayOptionCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_get_settings_is_cached(): void
    {
        PaymentDisplayOption::query()->delete();

        PaymentDisplayOption::create([
            'show_pay_publigo' => true,
            'show_pay_online' => true,
            'show_deferred_order' => true,
            'show_order_form' => true,
            'show_order_form_v2' => false,
            'show_order_form_alt' => true,
            'order_form_auto_fill_test_data' => false,
            'default_post_end_access_duration_value' => 2,
            'default_post_end_access_duration_unit' => 'months',
        ]);

        PaymentDisplayOption::getSettings();

        DB::enableQueryLog();

        PaymentDisplayOption::getSettings();

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_forget_settings_cache_forces_reload(): void
    {
        PaymentDisplayOption::query()->delete();

        PaymentDisplayOption::create([
            'show_pay_publigo' => true,
            'show_pay_online' => false,
            'show_deferred_order' => true,
            'show_order_form' => true,
            'show_order_form_v2' => false,
            'show_order_form_alt' => true,
            'order_form_auto_fill_test_data' => false,
            'default_post_end_access_duration_value' => 2,
            'default_post_end_access_duration_unit' => 'months',
        ]);

        PaymentDisplayOption::getSettings();
        PaymentDisplayOption::forgetSettingsCache();

        DB::enableQueryLog();

        $settings = PaymentDisplayOption::getSettings();

        $this->assertGreaterThanOrEqual(1, count(DB::getQueryLog()));
        $this->assertFalse($settings->show_pay_online);
        $this->assertFalse($settings->show_order_form_v2);
    }

    public function test_show_order_form_v2_defaults_to_false_and_is_cast_to_boolean(): void
    {
        $settings = new PaymentDisplayOption;

        $this->assertFalse($settings->show_order_form_v2);

        $settings->show_order_form_v2 = 1;

        $this->assertTrue($settings->show_order_form_v2);
    }
}
