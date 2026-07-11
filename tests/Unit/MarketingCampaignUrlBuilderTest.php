<?php

namespace Tests\Unit;

use App\Models\MarketingCampaign;
use App\Models\MarketingSourceType;
use App\Services\MarketingCampaignUrlBuilder;
use Tests\TestCase;

class MarketingCampaignUrlBuilderTest extends TestCase
{
    public function test_build_short_url_uses_campaign_code(): void
    {
        $builder = new MarketingCampaignUrlBuilder;

        $this->assertSame(
            rtrim((string) config('marketing.pnedu_public_url'), '/').'/l/1241',
            $builder->buildShortUrl('1241'),
        );
    }

    public function test_build_for_campaign_includes_short_link_with_campaign_code(): void
    {
        $sourceType = new MarketingSourceType([
            'utm_source' => 'newsletter',
            'default_utm_medium' => 'email',
        ]);

        $campaign = new MarketingCampaign([
            'campaign_code' => '1241',
            'course_id' => 527,
            'landing_target' => 'course_show',
        ]);
        $campaign->setRelation('sourceType', $sourceType);

        $urls = (new MarketingCampaignUrlBuilder)->buildForCampaign($campaign);

        $this->assertStringContainsString('/courses/527?', $urls['utm']);
        $this->assertStringContainsString('utm_campaign=1241', $urls['utm']);
        $this->assertSame(
            rtrim((string) config('marketing.pnedu_public_url'), '/').'/l/1241',
            $urls['short'],
        );
    }

    public function test_short_link_is_empty_without_course(): void
    {
        $sourceType = new MarketingSourceType([
            'utm_source' => 'newsletter',
            'default_utm_medium' => 'email',
        ]);

        $campaign = new MarketingCampaign([
            'campaign_code' => '9999',
            'course_id' => null,
        ]);
        $campaign->setRelation('sourceType', $sourceType);

        $urls = (new MarketingCampaignUrlBuilder)->buildForCampaign($campaign);

        $this->assertSame('', $urls['short']);
        $this->assertSame('', $urls['utm']);
    }

    public function test_build_for_campaign_includes_utm_content_when_set(): void
    {
        $sourceType = new MarketingSourceType([
            'utm_source' => 'facebook',
            'default_utm_medium' => 'paid',
        ]);

        $campaign = new MarketingCampaign([
            'campaign_code' => '1300',
            'course_id' => 100,
            'landing_target' => 'course_show',
            'utm_content' => 'remarketing',
        ]);
        $campaign->setRelation('sourceType', $sourceType);

        $urls = (new MarketingCampaignUrlBuilder)->buildForCampaign($campaign);

        $this->assertStringContainsString('utm_content=remarketing', $urls['utm']);
        $this->assertSame('remarketing', $urls['utm_content']);
    }

    public function test_build_for_campaign_falls_back_to_source_type_default_utm_content(): void
    {
        $sourceType = new MarketingSourceType([
            'utm_source' => 'facebook',
            'default_utm_medium' => 'paid',
            'default_utm_content' => 'prospecting',
        ]);

        $campaign = new MarketingCampaign([
            'campaign_code' => '1302',
            'course_id' => 100,
            'landing_target' => 'course_show',
            'utm_content' => null,
        ]);
        $campaign->setRelation('sourceType', $sourceType);

        $urls = (new MarketingCampaignUrlBuilder)->buildForCampaign($campaign);

        $this->assertStringContainsString('utm_content=prospecting', $urls['utm']);
        $this->assertSame('prospecting', $urls['utm_content']);
    }

    public function test_campaign_utm_content_overrides_source_type_default(): void
    {
        $sourceType = new MarketingSourceType([
            'utm_source' => 'facebook',
            'default_utm_medium' => 'paid',
            'default_utm_content' => 'prospecting',
        ]);

        $campaign = new MarketingCampaign([
            'campaign_code' => '1303',
            'course_id' => 100,
            'landing_target' => 'course_show',
            'utm_content' => 'carousel-ad',
        ]);
        $campaign->setRelation('sourceType', $sourceType);

        $urls = (new MarketingCampaignUrlBuilder)->buildForCampaign($campaign);

        $this->assertStringContainsString('utm_content=carousel-ad', $urls['utm']);
        $this->assertSame('carousel-ad', $urls['utm_content']);
    }

    public function test_campaign_order_form_path_uses_stored_legacy_variant(): void
    {
        $sourceType = new MarketingSourceType([
            'utm_source' => 'newsletter',
            'default_utm_medium' => 'email',
        ]);

        $campaign = new MarketingCampaign([
            'campaign_code' => '2002',
            'course_id' => 527,
            'landing_target' => 'order_form',
            'order_form_variant' => 'legacy',
        ]);
        $campaign->setRelation('sourceType', $sourceType);

        $urls = (new MarketingCampaignUrlBuilder)->buildForCampaign($campaign);

        $this->assertStringContainsString('/courses/527/order-form?', $urls['utm']);
        $this->assertStringContainsString('form_variant=legacy', $urls['utm']);
        $this->assertStringNotContainsString('order-form-v2', $urls['utm']);
    }

    public function test_build_for_campaign_uses_order_form_v2_path_when_configured(): void
    {
        $sourceType = new MarketingSourceType([
            'utm_source' => 'newsletter',
            'default_utm_medium' => 'email',
        ]);

        $campaign = new MarketingCampaign([
            'campaign_code' => '2001',
            'course_id' => 527,
            'landing_target' => 'order_form',
            'order_form_variant' => 'v2',
        ]);
        $campaign->setRelation('sourceType', $sourceType);

        $urls = (new MarketingCampaignUrlBuilder)->buildForCampaign($campaign);

        $this->assertStringContainsString('/courses/527/order-form?', $urls['utm']);
        $this->assertStringContainsString('form_variant=v2', $urls['utm']);
        $this->assertStringContainsString('/courses/527/order-form?', $urls['legacy']);
        $this->assertStringContainsString('form_variant=v2', $urls['legacy']);
    }

    public function test_campaign_order_form_global_omits_form_variant_from_urls(): void
    {
        $sourceType = new MarketingSourceType([
            'utm_source' => 'facebook',
            'default_utm_medium' => 'paid',
        ]);

        $campaign = new MarketingCampaign([
            'campaign_code' => '2003',
            'course_id' => 527,
            'landing_target' => 'order_form',
            'order_form_variant' => 'global',
        ]);
        $campaign->setRelation('sourceType', $sourceType);

        $urls = (new MarketingCampaignUrlBuilder)->buildForCampaign($campaign);

        $this->assertStringContainsString('/courses/527/order-form?', $urls['utm']);
        $this->assertStringNotContainsString('form_variant=', $urls['utm']);
        $this->assertStringContainsString('utm_campaign=2003', $urls['utm']);
        $this->assertStringContainsString('/courses/527/order-form?', $urls['legacy']);
        $this->assertStringNotContainsString('form_variant=', $urls['legacy']);
        $this->assertStringContainsString('fb=2003', $urls['legacy']);
    }
}
