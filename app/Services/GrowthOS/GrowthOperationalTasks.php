<?php

namespace App\Services\GrowthOS;

use App\Models\GrowthOS\GrowthCampaign;
use App\Models\GrowthOS\GrowthTask;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class GrowthOperationalTasks
{
    public const KEY_YOUTUBE_LIVE = 'youtube-live';

    public const KEY_TECHNICAL_TEST = 'technical-test';

    public const KEY_SOCIAL_REMINDER = 'social-reminder';

    public const KEY_LIVE_WEBINAR = 'live-webinar';

    public const KEY_CERTIFICATE_FORM = 'certificate-form';

    public const KEY_RECORDING = 'recording';

    public const KEY_TRANSCRIPTION = 'transcription';

    public const KEY_CERTIFICATE_TOPICS = 'certificate-topics';

    public const KEY_CONTENT_REPURPOSING = 'content-repurposing';

    /**
     * @return list<array{key: string, title: string, days: int, hours: int}>
     */
    public static function templates(): array
    {
        return [
            ['key' => self::KEY_YOUTUBE_LIVE, 'title' => 'YouTube Live', 'days' => -5, 'hours' => 0],
            ['key' => self::KEY_TECHNICAL_TEST, 'title' => 'Test techniczny', 'days' => -1, 'hours' => 0],
            ['key' => self::KEY_SOCIAL_REMINDER, 'title' => 'Social reminder', 'days' => 0, 'hours' => -3],
            ['key' => self::KEY_LIVE_WEBINAR, 'title' => 'Webinar', 'days' => 0, 'hours' => 0],
            ['key' => self::KEY_CERTIFICATE_FORM, 'title' => 'Formularz zaświadczenia', 'days' => 0, 'hours' => 0],
            ['key' => self::KEY_RECORDING, 'title' => 'Nagranie', 'days' => 1, 'hours' => 0],
            ['key' => self::KEY_TRANSCRIPTION, 'title' => 'Transkrypcja', 'days' => 1, 'hours' => 0],
            ['key' => self::KEY_CERTIFICATE_TOPICS, 'title' => 'Zagadnienia do zaświadczenia', 'days' => 1, 'hours' => 0],
            ['key' => self::KEY_CONTENT_REPURPOSING, 'title' => 'Content repurposing', 'days' => 3, 'hours' => 0],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::templates(), 'key');
    }

    public function ensureForCampaign(GrowthCampaign $campaign): void
    {
        foreach (self::templates() as $template) {
            GrowthTask::query()->firstOrCreate(
                [
                    'growth_campaign_id' => $campaign->id,
                    'key' => $template['key'],
                ],
                [
                    'title' => $template['title'],
                    'status' => GrowthTask::STATUS_TODO,
                    'due_at' => $this->dueAt($campaign->live_at, $template['days'], $template['hours']),
                    'growth_artifact_id' => null,
                    'assignee_user_id' => null,
                ],
            );
        }
    }

    public function dueAt(?CarbonInterface $liveAt, int $days, int $hours): ?Carbon
    {
        if ($liveAt === null) {
            return null;
        }

        return Carbon::instance($liveAt)->addDays($days)->addHours($hours);
    }
}
