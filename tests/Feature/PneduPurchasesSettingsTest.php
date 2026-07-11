<?php

namespace Tests\Feature;

use App\Models\PaymentDisplayOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PneduPurchasesSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_settings_page_shows_order_form_v2_option_as_test_feature(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('settings.pnedu-purchases.index'))
            ->assertOk()
            ->assertSee('Zamawiam szkolenie v2')
            ->assertSee('Bezpieczeństwo sprzedaży');
    }

    public function test_order_form_v2_flag_is_saved_and_settings_cache_is_cleared(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $settings = PaymentDisplayOption::getSettings();

        $this->assertFalse($settings->show_order_form_v2);
        $this->assertTrue(Cache::has(PaymentDisplayOption::SETTINGS_CACHE_KEY));

        $this->actingAs($user)
            ->post(route('settings.pnedu-purchases.store'), [
                'show_pay_publigo' => '1',
                'show_pay_online' => '1',
                'show_deferred_order' => '1',
                'show_order_form' => '1',
            'show_order_form_v2' => '1',
            'default_signup_order_form_variant' => 'v2',
            'show_order_form_alt' => '1',
                'default_post_end_access_duration_value' => 2,
                'default_post_end_access_duration_unit' => 'months',
            ])
            ->assertRedirect(route('settings.pnedu-purchases.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payment_display_options', [
            'id' => $settings->id,
            'show_order_form_v2' => true,
            'default_signup_order_form_variant' => 'v2',
        ]);
        $this->assertFalse(Cache::has(PaymentDisplayOption::SETTINGS_CACHE_KEY));
        $this->assertTrue(PaymentDisplayOption::getSettings()->show_order_form_v2);
    }

    public function test_cannot_save_when_both_order_form_variants_are_disabled(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $settings = PaymentDisplayOption::getSettings();

        $this->actingAs($user)
            ->post(route('settings.pnedu-purchases.store'), [
                'show_pay_publigo' => '1',
                'show_pay_online' => '1',
                'show_deferred_order' => '1',
                'show_order_form_alt' => '1',
                'default_post_end_access_duration_value' => 2,
                'default_post_end_access_duration_unit' => 'months',
            ])
            ->assertRedirect(route('settings.pnedu-purchases.index'))
            ->assertSessionHasErrors(['show_order_form', 'show_order_form_v2']);

        $this->assertTrue(PaymentDisplayOption::getSettings()->show_order_form);
        $this->assertFalse(PaymentDisplayOption::getSettings()->show_order_form_v2);
        $this->assertSame($settings->id, PaymentDisplayOption::getSettings()->id);
    }

    public function test_default_v2_requires_v2_checkbox_enabled(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('settings.pnedu-purchases.store'), [
                'show_pay_publigo' => '1',
                'show_pay_online' => '1',
                'show_deferred_order' => '1',
                'show_order_form' => '1',
                'default_signup_order_form_variant' => 'v2',
                'show_order_form_alt' => '1',
                'default_post_end_access_duration_value' => 2,
                'default_post_end_access_duration_unit' => 'months',
            ])
            ->assertRedirect(route('settings.pnedu-purchases.index'))
            ->assertSessionHasErrors('default_signup_order_form_variant');
    }

    public function test_default_legacy_requires_legacy_checkbox_enabled(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('settings.pnedu-purchases.store'), [
                'show_pay_publigo' => '1',
                'show_pay_online' => '1',
                'show_deferred_order' => '1',
                'show_order_form_v2' => '1',
                'default_signup_order_form_variant' => 'legacy',
                'show_order_form_alt' => '1',
                'default_post_end_access_duration_value' => 2,
                'default_post_end_access_duration_unit' => 'months',
            ])
            ->assertRedirect(route('settings.pnedu-purchases.index'))
            ->assertSessionHasErrors('default_signup_order_form_variant');
    }
}
