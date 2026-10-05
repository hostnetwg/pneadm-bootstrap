<?php

namespace App\Support\GrowthOS;

/**
 * Data the Sendy PNE mail shell needs beyond the editorial body (DEC-050).
 */
final class MailRenderContext
{
    /**
     * @param  list<array<string, mixed>>  $paidCourses
     */
    public function __construct(
        public readonly ?string $registrationUrl = null,
        public readonly ?string $youtubeLiveUrl = null,
        public readonly string $hostName = '',
        public readonly string $liveLabel = '',
        public readonly string $webinarTitle = '',
        public readonly string $webinarSubtitle = '',
        public readonly bool $showCertificate = false,
        public readonly bool $includeRoomCta = false,
        public readonly array $paidCourses = [],
        public readonly ?int $growthCampaignId = null,
        public readonly bool $isReminder = false,
    ) {}

    /**
     * @param  array<string, mixed>  $project
     * @param  array<string, mixed>  $material
     */
    public static function fromProject(array $project, array $material = [], bool $isReminder = false): self
    {
        $concept = is_array($project['concept'] ?? null) ? $project['concept'] : [];
        $includePaid = (bool) ($material['include_paid_offer'] ?? false);
        $snapshot = is_array($material['paid_offer_snapshot'] ?? null) ? $material['paid_offer_snapshot'] : [];
        $courses = $includePaid && is_array($snapshot['courses'] ?? null)
            ? array_values($snapshot['courses'])
            : [];

        $body = '';
        if (is_string($material['draft'] ?? null)) {
            $body = $material['draft'];
        } elseif (is_string($material['mail_body'] ?? null)) {
            $body = $material['mail_body'];
        }

        $liveLabel = trim((string) ($project['live_label'] ?? ''));
        if ($liveLabel === '') {
            $liveLabel = DemoTikWebinarProject::liveLabel($project);
        }

        return new self(
            registrationUrl: self::nullableUrl($project['registration_url'] ?? null),
            youtubeLiveUrl: self::nullableUrl($project['youtube_live_url'] ?? null),
            hostName: trim((string) ($project['host'] ?? '')),
            liveLabel: $liveLabel,
            webinarTitle: trim((string) ($concept['title'] ?? $project['topic'] ?? '')),
            webinarSubtitle: trim((string) ($concept['subtitle'] ?? '')),
            showCertificate: (bool) ($material['show_certificate'] ?? false),
            includeRoomCta: $isReminder && (
                str_contains($body, \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::ROOM_LINK_PLACEHOLDER)
                || ($material['force_room_cta'] ?? false) === true
            ),
            paidCourses: $courses,
            growthCampaignId: is_numeric($project['growth_campaign_id'] ?? null)
                ? (int) $project['growth_campaign_id']
                : null,
            isReminder: $isReminder,
        );
    }

    private static function nullableUrl(mixed $value): ?string
    {
        $url = trim((string) $value);

        return $url !== '' ? $url : null;
    }
}
