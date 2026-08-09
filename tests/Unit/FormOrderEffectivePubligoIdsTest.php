<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\FormOrder;
use Tests\TestCase;

class FormOrderEffectivePubligoIdsTest extends TestCase
{
    public function test_uses_order_publigo_ids_when_present(): void
    {
        $order = new FormOrder([
            'publigo_product_id' => 999,
            'publigo_price_id' => 3,
        ]);

        $this->assertSame(999, $order->effectivePubligoProductId());
        $this->assertSame(3, $order->effectivePubligoPriceId());
        $this->assertTrue($order->hasEffectivePubligoIds());
    }

    public function test_falls_back_to_course_id_old_and_default_price(): void
    {
        $order = new FormOrder([
            'product_id' => 534,
            'publigo_product_id' => null,
            'publigo_price_id' => null,
        ]);
        $order->setRelation('course', new Course([
            'id' => 534,
            'id_old' => '1111111',
        ]));

        $this->assertSame(1111111, $order->effectivePubligoProductId());
        $this->assertSame(1, $order->effectivePubligoPriceId());
        $this->assertTrue($order->hasEffectivePubligoIds());
    }

    public function test_returns_null_when_neither_order_nor_course_has_product_id(): void
    {
        $order = new FormOrder([
            'publigo_product_id' => null,
            'publigo_price_id' => null,
        ]);
        $order->setRelation('course', new Course([
            'id' => 1,
            'id_old' => null,
        ]));

        $this->assertNull($order->effectivePubligoProductId());
        $this->assertNull($order->effectivePubligoPriceId());
        $this->assertFalse($order->hasEffectivePubligoIds());
    }

    public function test_keeps_order_price_when_product_comes_from_course(): void
    {
        $order = new FormOrder([
            'publigo_product_id' => null,
            'publigo_price_id' => 7,
        ]);
        $order->setRelation('course', new Course([
            'id' => 10,
            'id_old' => '55',
        ]));

        $this->assertSame(55, $order->effectivePubligoProductId());
        $this->assertSame(7, $order->effectivePubligoPriceId());
    }
}
