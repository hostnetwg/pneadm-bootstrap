<?php

namespace App\Enums\Analytics;

enum CampaignContentDepth: string
{
    case FullOffer = 'full_offer';
    case ShortOffer = 'short_offer';
    case VideoPitch = 'video_pitch';
    case FacebookPost = 'facebook_post';
    case MetaAd = 'meta_ad';
    case Reminder = 'reminder';
    case LastCall = 'last_call';
    case LeadMagnet = 'lead_magnet';
    case Retargeting = 'retargeting';
}
