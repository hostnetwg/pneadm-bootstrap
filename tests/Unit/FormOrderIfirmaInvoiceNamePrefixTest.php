<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use App\Models\OrderItem;
use App\Models\Product;
use Tests\TestCase;

class FormOrderIfirmaInvoiceNamePrefixTest extends TestCase
{
    public function test_training_order_uses_szkolenie_prefix(): void
    {
        $order = new FormOrder([
            'order_kind' => 'training',
            'product_name' => 'AI w edukacji',
        ]);

        $this->assertSame('SZKOLENIE', $order->ifirmaInvoiceNamePrefix());
        $this->assertSame('SZKOLENIE: AI w edukacji', $order->withIfirmaInvoiceNamePrefix('AI w edukacji'));
    }

    public function test_null_order_kind_uses_szkolenie_prefix(): void
    {
        $order = new FormOrder([
            'product_name' => 'AI w edukacji',
        ]);

        $this->assertSame('SZKOLENIE', $order->ifirmaInvoiceNamePrefix());
    }

    public function test_online_course_product_order_uses_kurs_prefix(): void
    {
        $order = new FormOrder([
            'order_kind' => 'product',
            'product_name' => 'TIK w pracy nauczyciela',
        ]);
        $order->setRelation('orderItems', collect([
            new OrderItem(['product_type' => Product::TYPE_ONLINE_COURSE]),
        ]));

        $this->assertSame('KURS', $order->ifirmaInvoiceNamePrefix());
        $this->assertSame(
            'KURS: TIK w pracy nauczyciela',
            $order->withIfirmaInvoiceNamePrefix('TIK w pracy nauczyciela')
        );
    }

    public function test_product_order_without_items_defaults_to_kurs(): void
    {
        $order = new FormOrder([
            'order_kind' => 'product',
            'product_name' => 'Produkt bez pozycji',
        ]);
        $order->setRelation('orderItems', collect());

        $this->assertSame('KURS', $order->ifirmaInvoiceNamePrefix());
    }

    public function test_ebook_product_order_uses_ebook_prefix(): void
    {
        $order = new FormOrder([
            'order_kind' => 'product',
            'product_name' => 'Pakiet materiałów',
        ]);
        $order->setRelation('orderItems', collect([
            new OrderItem(['product_type' => Product::TYPE_EBOOK]),
        ]));

        $this->assertSame('E-BOOK', $order->ifirmaInvoiceNamePrefix());
        $this->assertSame(
            'E-BOOK: Pakiet materiałów',
            $order->withIfirmaInvoiceNamePrefix('Pakiet materiałów')
        );
    }

    public function test_does_not_duplicate_existing_kind_prefix(): void
    {
        $order = new FormOrder(['order_kind' => 'product']);
        $order->setRelation('orderItems', collect([
            new OrderItem(['product_type' => Product::TYPE_ONLINE_COURSE]),
        ]));

        $this->assertSame('KURS: TIK', $order->withIfirmaInvoiceNamePrefix('KURS: TIK'));
        $this->assertSame('SZKOLENIE: AI', $order->withIfirmaInvoiceNamePrefix('SZKOLENIE: AI'));
        $this->assertSame('E-BOOK: Pakiet', $order->withIfirmaInvoiceNamePrefix('E-BOOK: Pakiet'));
    }
}
