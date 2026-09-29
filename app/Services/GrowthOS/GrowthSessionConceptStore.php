<?php

namespace App\Services\GrowthOS;

use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthCampaign;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Zapisuje z sesyjnego prototypu tylko kampanię i materiał koncepcji.
 */
class GrowthSessionConceptStore
{
    public const CONCEPT_KEY = 'concept';

    public const CONCEPT_TYPE = 'concept';

    public const SCHEMA_VERSION = 1;

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    public function createCampaign(array $project, User $owner): array
    {
        $topic = mb_substr(trim((string) ($project['topic'] ?? '')), 0, 180);
        $liveAt = $this->liveAt(
            (string) ($project['live_date'] ?? ''),
            (string) ($project['live_time'] ?? ''),
        );

        $campaign = GrowthCampaign::query()->create([
            'name' => $topic !== '' ? $topic : 'Webinar TIK',
            'type' => mb_substr(trim((string) ($project['type'] ?? 'webinar')), 0, 40),
            'status' => $this->databaseStatus((string) ($project['status'] ?? 'PLANNING')),
            'goal' => mb_substr(trim((string) ($project['goal'] ?? '')), 0, 120) ?: null,
            'owner_user_id' => $owner->id,
            'primary_instructor_id' => null,
            'working_topic' => $topic !== '' ? $topic : null,
            'live_at' => $liveAt,
        ]);

        $project['growth_campaign_id'] = $campaign->id;

        return $project;
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public function syncStatus(array $project): void
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return;
        }

        GrowthCampaign::query()->whereKey((int) $campaignId)->update([
            'status' => $this->databaseStatus((string) ($project['status'] ?? 'PLANNING')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public function persistConcept(array $project, User $actor): void
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        $concept = $project['concept'] ?? null;
        if (! is_numeric($campaignId) || ! is_array($concept)) {
            return;
        }

        $artifact = GrowthArtifact::query()->firstOrNew([
            'growth_campaign_id' => (int) $campaignId,
            'key' => self::CONCEPT_KEY,
        ]);

        $artifact->type = self::CONCEPT_TYPE;
        $artifact->status = GrowthArtifact::STATUS_DRAFT;
        $artifact->title = mb_substr(trim((string) ($concept['title'] ?? '')), 0, 180) ?: null;
        $artifact->schema_version = self::SCHEMA_VERSION;
        $artifact->version = $artifact->exists ? ((int) $artifact->version + 1) : 1;
        $artifact->payload = $concept;
        if (! $artifact->exists) {
            $artifact->created_by_user_id = $actor->id;
        }
        $artifact->save();
    }

    public function latestOwnedCampaign(User $owner): ?GrowthCampaign
    {
        $campaign = GrowthCampaign::query()
            ->where('owner_user_id', $owner->id)
            ->latest('id')
            ->first();

        return $campaign instanceof GrowthCampaign ? $campaign : null;
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    public function overlay(array $project): array
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return $project;
        }

        $campaign = GrowthCampaign::query()->find((int) $campaignId);
        if (! $campaign instanceof GrowthCampaign) {
            return $project;
        }

        if (filled($campaign->working_topic)) {
            $project['topic'] = $campaign->working_topic;
        }
        if (filled($campaign->goal)) {
            $project['goal'] = $campaign->goal;
        }
        $project['type'] = $campaign->type;
        $project['status'] = $this->sessionStatus($campaign->status);
        if ($campaign->live_at !== null) {
            $project['live_date'] = $campaign->live_at->toDateString();
            $project['live_time'] = $campaign->live_at->format('H:i');
        }

        $artifact = GrowthArtifact::query()
            ->where('growth_campaign_id', $campaign->id)
            ->where('key', self::CONCEPT_KEY)
            ->first();

        if ($artifact instanceof GrowthArtifact && is_array($artifact->payload)) {
            $project['concept'] = $artifact->payload;
        }

        return $project;
    }

    private function liveAt(string $date, string $time): ?CarbonImmutable
    {
        if ($date === '' || $time === '') {
            return null;
        }

        return CarbonImmutable::parse($date.' '.$time);
    }

    private function databaseStatus(string $status): string
    {
        return match ($status) {
            'PREPARING' => GrowthCampaign::STATUS_PREPARING,
            default => GrowthCampaign::STATUS_PLANNING,
        };
    }

    private function sessionStatus(string $status): string
    {
        return match ($status) {
            GrowthCampaign::STATUS_PREPARING => 'PREPARING',
            default => 'PLANNING',
        };
    }
}
