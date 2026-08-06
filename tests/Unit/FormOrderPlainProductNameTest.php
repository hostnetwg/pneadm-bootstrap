<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use Tests\TestCase;

class FormOrderPlainProductNameTest extends TestCase
{
    public function test_plain_product_name_strips_nbsp_entities(): void
    {
        $name = FormOrder::plainProductName(
            'Nowe przepisy żywieniowe w szkole i&nbsp;przedszkolu, obowiązujące od 1 września 2026&nbsp;r.'
        );

        $this->assertSame(
            'Nowe przepisy żywieniowe w szkole i przedszkolu, obowiązujące od 1 września 2026 r.',
            $name
        );
    }

    public function test_display_product_name_accessor_uses_plain_text(): void
    {
        $order = new FormOrder([
            'product_name' => 'Szkolenie A&nbsp;i B',
        ]);

        $this->assertSame('Szkolenie A i B', $order->display_product_name);
    }

    public function test_plain_product_name_returns_fallback_when_empty(): void
    {
        $this->assertSame('—', FormOrder::plainProductName(null));
        $this->assertSame('brak', FormOrder::plainProductName('   ', 'brak'));
    }
}
