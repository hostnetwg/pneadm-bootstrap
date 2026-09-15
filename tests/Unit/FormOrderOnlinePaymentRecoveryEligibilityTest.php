<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use Tests\TestCase;

class FormOrderOnlinePaymentRecoveryEligibilityTest extends TestCase
{
    public function test_training_unpaid_online_is_eligible(): void
    {
        $order = new FormOrder([
            'order_kind' => 'training',
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_CANCELLED,
            'cancelled_at' => null,
            'invoice_number' => null,
            'status_completed' => 0,
        ]);

        $this->assertTrue($order->isEligibleForOnlinePaymentRecoveryEmail());
    }

    public function test_product_unpaid_online_is_eligible(): void
    {
        $order = new FormOrder([
            'order_kind' => 'product',
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_FAILED,
            'cancelled_at' => null,
            'invoice_number' => null,
            'status_completed' => 0,
        ]);

        $this->assertTrue($order->isEligibleForOnlinePaymentRecoveryEmail());
    }

    public function test_cancelled_order_is_not_eligible(): void
    {
        $order = new FormOrder([
            'order_kind' => 'product',
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_CANCELLED,
            'cancelled_at' => now('UTC'),
            'invoice_number' => null,
            'status_completed' => 0,
        ]);

        $this->assertFalse($order->isEligibleForOnlinePaymentRecoveryEmail());
    }
}
