<?php

namespace Tests\Unit\GrowthOS;

use App\Services\GrowthOS\AI\Support\AddressFormPolicy;
use Tests\TestCase;

class AddressFormPolicyTest extends TestCase
{
    public function test_normalize_defaults_to_ty(): void
    {
        $this->assertSame(AddressFormPolicy::TY, AddressFormPolicy::normalize(null));
        $this->assertSame(AddressFormPolicy::TY, AddressFormPolicy::normalize(''));
        $this->assertSame(AddressFormPolicy::TY, AddressFormPolicy::normalize('weird'));
        $this->assertSame(AddressFormPolicy::PANSTWO, AddressFormPolicy::normalize('panstwo'));
    }

    public function test_detect_override_phrases(): void
    {
        $this->assertSame(AddressFormPolicy::PANSTWO, AddressFormPolicy::detectOverride('Ten mail napisz formalnie, w formie Państwo.'));
        $this->assertSame(AddressFormPolicy::TY, AddressFormPolicy::detectOverride('Ten post napisz bezpośrednio na Ty.'));
        $this->assertNull(AddressFormPolicy::detectOverride('Napisz krócej i mniej marketingowo.'));
        $this->assertNull(AddressFormPolicy::detectOverride('Bardziej profesjonalnie.'));
    }

    public function test_resolve_uses_project_unless_override_or_iterate_previous(): void
    {
        $base = AddressFormPolicy::resolve(AddressFormPolicy::TY, 'Napisz krócej');
        $this->assertSame(AddressFormPolicy::TY, $base['form']);
        $this->assertFalse($base['overridden']);

        $override = AddressFormPolicy::resolve(AddressFormPolicy::TY, 'Pisz na Państwo');
        $this->assertSame(AddressFormPolicy::PANSTWO, $override['form']);
        $this->assertTrue($override['overridden']);

        $iterate = AddressFormPolicy::resolve(AddressFormPolicy::TY, 'Zostaw ton, ale krócej', AddressFormPolicy::PANSTWO);
        $this->assertSame(AddressFormPolicy::PANSTWO, $iterate['form']);
        $this->assertTrue($iterate['overridden']);
    }

    public function test_prompt_block_covers_both_forms(): void
    {
        $block = AddressFormPolicy::promptBlock(AddressFormPolicy::CHANNEL_WRITTEN);
        $this->assertStringContainsString('style.address_form', $block);
        $this->assertStringContainsString('otrzymasz', $block);
        $this->assertStringContainsString('otrzymają Państwo', $block);
        $this->assertStringContainsString('pokażę', $block);

        $live = AddressFormPolicy::promptBlock(AddressFormPolicy::CHANNEL_LIVE);
        $this->assertStringContainsString('Wy/Wam', $live);
    }
}
