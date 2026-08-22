<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormOrdersAbandonedOnlineFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_abandoned_online_filter_returns_only_matching_orders(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $abandoned = FormOrder::create([
            'product_name' => 'Test abandoned',
            'order_date' => now('UTC')->subMinutes(90),
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_FAILED,
            'status_completed' => 0,
            'orderer_email' => 'abandoned@example.test',
        ]);

        $freshAwaiting = FormOrder::create([
            'product_name' => 'Test fresh awaiting',
            'order_date' => now('UTC')->subMinutes(10),
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_AWAITING_PAYMENT,
            'status_completed' => 0,
            'orderer_email' => 'fresh@example.test',
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.index', [
            'quick' => 'all',
            'abandoned_online' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('PORZUCONA PŁATNOŚĆ', false);
        $response->assertSee('ID: #'.$abandoned->id, false);
        $response->assertDontSee('ID: #'.$freshAwaiting->id, false);
        $response->assertSee('Formularz: porzucona płatność online', false);
    }

    public function test_scope_abandoned_unpaid_online_excludes_paid_and_fresh_awaiting(): void
    {
        FormOrder::create([
            'product_name' => 'Failed abandoned',
            'order_date' => now('UTC')->subHour(),
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_FAILED,
            'status_completed' => 0,
            'orderer_email' => 'failed@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Paid online',
            'order_date' => now('UTC')->subDays(2),
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_PAID,
            'status_completed' => 0,
            'orderer_email' => 'paid@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Fresh awaiting',
            'order_date' => now('UTC')->subMinutes(5),
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_AWAITING_PAYMENT,
            'status_completed' => 0,
            'orderer_email' => 'await@example.test',
        ]);

        $ids = FormOrder::query()->abandonedUnpaidOnline()->pluck('product_name')->all();

        $this->assertContains('Failed abandoned', $ids);
        $this->assertNotContains('Paid online', $ids);
        $this->assertNotContains('Fresh awaiting', $ids);
    }
}
