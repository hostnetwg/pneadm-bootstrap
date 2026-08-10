<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use Tests\TestCase;

class FormOrderUnpaidOnlineWarningTest extends TestCase
{
    public function test_warns_for_online_gateway_when_not_paid(): void
    {
        $order = new FormOrder;
        $order->forceFill([
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_AWAITING_PAYMENT,
        ]);
        $this->assertTrue($order->shouldWarnUnpaidOnlineGateway());

        $order->payment_status = FormOrder::PAYMENT_STATUS_CANCELLED;
        $this->assertTrue($order->shouldWarnUnpaidOnlineGateway());

        $order->payment_status = FormOrder::PAYMENT_STATUS_FAILED;
        $this->assertTrue($order->shouldWarnUnpaidOnlineGateway());
    }

    public function test_no_warning_when_online_paid_or_deferred(): void
    {
        $order = new FormOrder;
        $order->forceFill([
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_PAID,
        ]);
        $this->assertFalse($order->shouldWarnUnpaidOnlineGateway());

        $order->forceFill([
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $this->assertFalse($order->shouldWarnUnpaidOnlineGateway());
    }
}
