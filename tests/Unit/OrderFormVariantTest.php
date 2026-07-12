<?php

namespace Tests\Unit;

use App\Support\OrderFormVariant;
use Tests\TestCase;

class OrderFormVariantTest extends TestCase
{
    public function test_global_campaign_variant_omits_gateway_query(): void
    {
        $this->assertSame([], OrderFormVariant::gatewayQuery(OrderFormVariant::GLOBAL));
        $this->assertTrue(OrderFormVariant::usesGlobalGateway(OrderFormVariant::GLOBAL));
        $this->assertFalse(OrderFormVariant::usesGlobalGateway(OrderFormVariant::LEGACY));
        $this->assertSame(
            OrderFormVariant::GLOBAL,
            OrderFormVariant::storedCampaignVariant('global')
        );
        $this->assertSame(
            OrderFormVariant::LEGACY,
            OrderFormVariant::normalizeCampaignVariant('unknown')
        );
        $this->assertSame(
            OrderFormVariant::LEGACY,
            OrderFormVariant::normalize('global')
        );
    }

    public function test_campaign_label_includes_global(): void
    {
        $this->assertStringContainsString(
            'bez form_variant',
            OrderFormVariant::label(OrderFormVariant::GLOBAL)
        );
    }

    public function test_admin_short_label_for_stored_order_variant(): void
    {
        $this->assertSame('Formularz V2', OrderFormVariant::adminShortLabel('v2'));
        $this->assertSame('Formularz legacy', OrderFormVariant::adminShortLabel('legacy'));
        $this->assertSame('Legacy (domyślnie)', OrderFormVariant::adminShortLabel(null));
        $this->assertSame('Legacy (domyślnie)', OrderFormVariant::adminShortLabel(''));
    }
}
