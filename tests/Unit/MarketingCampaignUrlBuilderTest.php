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
}
