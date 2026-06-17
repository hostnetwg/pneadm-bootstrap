<?php

namespace Tests\Unit;

use App\Models\PaymentDisplayOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PaymentDisplayOptionUnrestrictedAutoFillExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_get_settings_auto_disables_expired_unrestricted_option_on_production(): void
    {
        $this->app['env'] = 'production';
        Carbon::setTestNow('2026-06-17 12:00:00');

        PaymentDisplayOption::query()->delete();
        PaymentDisplayOption::create([
            'show_pay_publigo' => true,
            'show_pay_online' => true,
            'show_deferred_order' => true,
            'show_order_form' => true,
            'show_order_form_alt' => true,
            'order_form_auto_fill_test_data' => true,
            'order_form_auto_fill_test_data_enabled_at' => Carbon::parse('2026-06-17 11:58:00'),
            'order_form_auto_fill_test_data_developers_only' => true,
            'default_post_end_access_duration_value' => 2,
            'default_post_end_access_duration_unit' => 'months',
        ]);

        $settings = PaymentDisplayOption::getSettings();

        $this->assertFalse($settings->order_form_auto_fill_test_data);
        $this->assertNull($settings->order_form_auto_fill_test_data_enabled_at);
        $this->assertTrue($settings->order_form_auto_fill_test_data_developers_only);

        Carbon::setTestNow();
    }

    public function test_get_settings_keeps_unrestricted_option_within_ttl_on_production(): void
    {
        $this->app['env'] = 'production';
        Carbon::setTestNow('2026-06-17 12:00:00');

        PaymentDisplayOption::query()->delete();
        PaymentDisplayOption::create([
            'show_pay_publigo' => true,
            'show_pay_online' => true,
            'show_deferred_order' => true,
            'show_order_form' => true,
            'show_order_form_alt' => true,
            'order_form_auto_fill_test_data' => true,
            'order_form_auto_fill_test_data_enabled_at' => Carbon::parse('2026-06-17 11:59:30'),
            'order_form_auto_fill_test_data_developers_only' => false,
            'default_post_end_access_duration_value' => 2,
            'default_post_end_access_duration_unit' => 'months',
        ]);

        $settings = PaymentDisplayOption::getSettings();

        $this->assertTrue($settings->order_form_auto_fill_test_data);

        Carbon::setTestNow();
    }

    public function test_get_settings_does_not_auto_disable_on_local(): void
    {
        $this->app['env'] = 'local';
        Carbon::setTestNow('2026-06-17 12:00:00');

        PaymentDisplayOption::query()->delete();
        PaymentDisplayOption::create([
            'show_pay_publigo' => true,
            'show_pay_online' => true,
            'show_deferred_order' => true,
            'show_order_form' => true,
            'show_order_form_alt' => true,
            'order_form_auto_fill_test_data' => true,
            'order_form_auto_fill_test_data_enabled_at' => Carbon::parse('2026-06-17 10:00:00'),
            'order_form_auto_fill_test_data_developers_only' => false,
            'default_post_end_access_duration_value' => 2,
            'default_post_end_access_duration_unit' => 'months',
        ]);

        $settings = PaymentDisplayOption::getSettings();

        $this->assertTrue($settings->order_form_auto_fill_test_data);

        Carbon::setTestNow();
    }
}
