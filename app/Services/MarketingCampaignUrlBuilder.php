<?php

namespace App\Services;

use App\Models\MarketingCampaign;
use App\Models\MarketingSourceType;

class MarketingCampaignUrlBuilder
{
    public function pneduBaseUrl(): string
    {
        return rtrim((string) config('marketing.pnedu_public_url', 'https://pnedu.pl'), '/');
    }

    /**
     * @return array{utm: string, legacy: string, short: string, utm_source: string, utm_medium: string, utm_campaign: string, utm_content: ?string}
     */
    public function buildForCampaign(
        MarketingCampaign $campaign,
        ?int $courseId = null,
        ?int $priceVariantId = null,
        ?string $landingTarget = null,
    ): array {
        $campaign->loadMissing('sourceType');

        $courseId = $courseId ?? $campaign->course_id;
        if (! $courseId) {
            $utmCampaign = (string) $campaign->campaign_code;

            return [
                'utm' => '',
                'legacy' => '',
                'short' => '',
                'utm_source' => $this->resolveUtmSource($campaign->sourceType),
                'utm_medium' => $this->resolveUtmMedium($campaign),
                'utm_campaign' => $utmCampaign,
                'utm_content' => $this->resolveUtmContent($campaign),
            ];
        }

        $landing = $landingTarget ?? $campaign->landing_target ?? 'course_show';
        $path = $landing === 'order_form'
            ? "/courses/{$courseId}/order-form"
            : "/courses/{$courseId}";

        $utmSource = $this->resolveUtmSource($campaign->sourceType);
        $utmMedium = $this->resolveUtmMedium($campaign);
        $utmCampaign = (string) $campaign->campaign_code;

        $utmQuery = http_build_query($this->utmQueryParams($campaign, $utmSource, $utmMedium, $utmCampaign));

        if ($landing === 'order_form' && $priceVariantId) {
            $utmQuery = 'price_variant_id='.$priceVariantId.'&'.$utmQuery;
        }

        $legacyQuery = $landing === 'order_form' && $priceVariantId
            ? 'price_variant_id='.$priceVariantId.'&fb='.rawurlencode($utmCampaign)
            : 'fb='.rawurlencode($utmCampaign);

        return [
            'utm' => $this->pneduBaseUrl().$path.'?'.$utmQuery,
            'legacy' => $this->pneduBaseUrl().$path.'?'.$legacyQuery,
            'short' => $this->buildShortUrl($utmCampaign),
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_content' => $this->resolveUtmContent($campaign),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function utmQueryParams(
        MarketingCampaign $campaign,
        string $utmSource,
        string $utmMedium,
        string $utmCampaign,
    ): array {
        $params = [
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
        ];

        $utmContent = $this->resolveUtmContent($campaign);
        if ($utmContent !== null) {
            $params['utm_content'] = $utmContent;
        }

        return $params;
    }

    public function resolveUtmContent(MarketingCampaign $campaign): ?string
    {
        $content = trim((string) ($campaign->utm_content ?? ''));

        if ($content !== '') {
            return $content;
        }

        $default = trim((string) ($campaign->sourceType?->default_utm_content ?? ''));

        return $default !== '' ? $default : null;
    }

    public function buildShortUrl(string $campaignCode): string
    {
        return $this->pneduBaseUrl().'/l/'.rawurlencode($campaignCode);
    }

    public function resolveUtmSource(?MarketingSourceType $sourceType): string
    {
        if (! $sourceType) {
            return 'other';
        }

        if (filled($sourceType->utm_source)) {
            return (string) $sourceType->utm_source;
        }

        return match ($sourceType->slug) {
            'email' => 'newsletter',
            'website' => 'pnedu',
            'training' => 'webinar',
            default => (string) $sourceType->slug,
        };
    }

    public function resolveUtmMedium(MarketingCampaign $campaign): string
    {
        if (filled($campaign->utm_medium)) {
            return (string) $campaign->utm_medium;
        }

        $default = $campaign->sourceType?->default_utm_medium;

        return filled($default) ? (string) $default : 'paid';
    }
}
