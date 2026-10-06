<?php

namespace App\Services\GrowthOS;

use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthArtifactImage;
use App\Models\GrowthOS\GrowthArtifactVersion;
use App\Models\GrowthOS\GrowthCampaign;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\User;
use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Services\GrowthOS\AI\Support\AddressFormPolicy;
use App\Support\GrowthOS\DemoTikWebinarProject;
use App\Support\GrowthOS\MailTemplates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Zapisuje z sesyjnego prototypu kampanię, kierunek, koncepcję i dziesięć materiałów roboczych.
 */
class GrowthSessionConceptStore
{
    public const CONCEPT_KEY = 'concept';

    public const CONCEPT_TYPE = 'concept';

    public const MATERIAL_TYPE = 'material';

    public const SCHEMA_VERSION = 1;

    public const DIRECTION_KEY = 'direction';

    public const DIRECTION_TYPE = 'direction';

    public const DECISION_DIRECTION_APPROVAL = 'direction_approval';

    public const DECISION_CONCEPT_APPROVAL = 'concept_approval';

    public const DECISION_CONCEPT_AI_APPLY = 'concept_ai_apply';

    public const DECISION_CONCEPT_AI_REJECT = 'concept_ai_reject';

    public const DECISION_MATERIAL_AI_APPLY = 'material_ai_apply';

    public const DECISION_MATERIAL_AI_REJECT = 'material_ai_reject';

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
            'host_name' => $this->hostName((string) ($project['host'] ?? '')),
            'owner_user_id' => $owner->id,
            'primary_instructor_id' => $this->instructorId($project['host_instructor_id'] ?? null),
            'communication_voice_instructor_id' => $this->instructorId($project['voice_instructor_id'] ?? null),
            'address_form' => AddressFormPolicy::normalize($project['address_form'] ?? null),
            'working_topic' => $topic !== '' ? $topic : null,
            'live_at' => $liveAt,
        ]);

        $project['growth_campaign_id'] = $campaign->id;
        app(GrowthOperationalTasks::class)->ensureForCampaign($campaign);

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

        $payload = $concept;
        unset($payload['ai_origin']);
        $origin = DemoTikWebinarProject::normalizeAiOrigin($project['concept_ai_origin'] ?? null);
        if ($origin === null && $artifact->exists && is_array($artifact->payload['ai_origin'] ?? null)) {
            $origin = DemoTikWebinarProject::normalizeAiOrigin($artifact->payload['ai_origin']);
        }
        if ($origin !== null) {
            $payload['ai_origin'] = $origin;
        }
        $artifact->payload = $payload;

        if (! $artifact->exists) {
            $artifact->created_by_user_id = $actor->id;
        }
        $artifact->save();
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public function persistPeople(array $project): void
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return;
        }

        GrowthCampaign::query()->whereKey((int) $campaignId)->update([
            'host_name' => $this->hostName((string) ($project['host'] ?? '')),
            'primary_instructor_id' => $this->instructorId($project['host_instructor_id'] ?? null),
            'communication_voice_instructor_id' => $this->instructorId($project['voice_instructor_id'] ?? null),
            'address_form' => AddressFormPolicy::normalize($project['address_form'] ?? null),
        ]);
    }

    /**
     * Persist campaign working topic / list name from the workspace project.
     *
     * @param  array<string, mixed>  $project
     */
    public function persistTopic(array $project): void
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return;
        }

        $topic = mb_substr(trim((string) ($project['topic'] ?? '')), 0, 180);
        if ($topic === '') {
            return;
        }

        GrowthCampaign::query()->whereKey((int) $campaignId)->update([
            'name' => $topic,
            'working_topic' => $topic,
        ]);
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public function persistSchedule(array $project): void
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return;
        }

        $liveAt = $this->liveAt(
            (string) ($project['live_date'] ?? ''),
            (string) ($project['live_time'] ?? ''),
        );

        $campaign = GrowthCampaign::query()->find((int) $campaignId);
        if (! $campaign instanceof GrowthCampaign) {
            return;
        }

        $campaign->live_at = $liveAt;
        $campaign->save();

        app(GrowthOperationalTasks::class)->ensureForCampaign($campaign);
        app(GrowthOperationalTasks::class)->resyncDueDates($campaign->fresh());
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public function persistLinks(array $project): void
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return;
        }

        GrowthCampaign::query()->whereKey((int) $campaignId)->update([
            'registration_url' => self::nullableString($project['registration_url'] ?? null),
            'youtube_live_url' => self::nullableString($project['youtube_live_url'] ?? null),
        ]);
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    public function latestOwnedCampaign(User $owner): ?GrowthCampaign
    {
        $campaign = $this->ownedCampaigns($owner)->first();

        return $campaign instanceof GrowthCampaign ? $campaign : null;
    }

    /**
     * @return Collection<int, GrowthCampaign>
     */
    public function ownedCampaigns(User $owner): Collection
    {
        return GrowthCampaign::query()
            ->where('owner_user_id', $owner->id)
            ->latest('id')
            ->get();
    }

    public function ownedCampaign(User $owner, int $campaignId): ?GrowthCampaign
    {
        $campaign = GrowthCampaign::query()
            ->where('owner_user_id', $owner->id)
            ->whereKey($campaignId)
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
        if (filled($campaign->host_name)) {
            $project['host'] = $campaign->host_name;
        }
        $project['host_instructor_id'] = $this->instructorId($campaign->primary_instructor_id);
        $project['voice_instructor_id'] = $this->instructorId($campaign->communication_voice_instructor_id);
        $project['address_form'] = AddressFormPolicy::normalize($campaign->address_form ?? null);
        $project['type'] = $campaign->type;
        $project['status'] = $this->sessionStatus($campaign->status);
        if ($campaign->live_at !== null) {
            $project['live_date'] = $campaign->live_at->toDateString();
            $project['live_time'] = $campaign->live_at->format('H:i');
        }
        $project['registration_url'] = is_string($campaign->registration_url) ? $campaign->registration_url : '';
        $project['youtube_live_url'] = is_string($campaign->youtube_live_url) ? $campaign->youtube_live_url : '';

        $artifact = GrowthArtifact::query()
            ->where('growth_campaign_id', $campaign->id)
            ->where('key', self::CONCEPT_KEY)
            ->first();

        if ($artifact instanceof GrowthArtifact && is_array($artifact->payload)) {
            $conceptPayload = $artifact->payload;
            $conceptOrigin = DemoTikWebinarProject::normalizeAiOrigin($conceptPayload['ai_origin'] ?? null);
            unset($conceptPayload['ai_origin']);
            $project['concept'] = $conceptPayload;
            if ($conceptOrigin !== null) {
                $project['concept_ai_origin'] = $conceptOrigin;
            }
        }

        $project = $this->overlayDirection($project, $campaign->id);
        $project = $this->overlayMaterials($project, $campaign->id);

        $approval = $this->latestConceptApproval((int) $campaign->id);
        $completed = $project['completed_steps'] ?? [];
        if (! is_array($completed)) {
            $completed = [];
        }
        if ($approval instanceof GrowthDecision && $approval->status === GrowthDecision::STATUS_APPROVED) {
            $completed['concept'] = $approval->decided_at?->toIso8601String() ?? now()->toIso8601String();
        } elseif ($approval instanceof GrowthDecision) {
            unset($completed['concept']);
        }

        $directionApproval = $this->latestDirectionApproval((int) $campaign->id);
        if ($directionApproval instanceof GrowthDecision && $directionApproval->status === GrowthDecision::STATUS_APPROVED) {
            $completed['direction'] = $directionApproval->decided_at?->toIso8601String() ?? now()->toIso8601String();
        } elseif ($directionApproval instanceof GrowthDecision) {
            unset($completed['direction']);
        }
        $project['completed_steps'] = $completed;

        return $project;
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  array<string, mixed>  $meta
     */
    public function recordConceptDecision(
        array $project,
        User $actor,
        string $type,
        string $status,
        string $question,
        string $decision,
        array $meta = [],
        ?string $artifactKey = null,
    ): void {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return;
        }

        $artifact = GrowthArtifact::query()
            ->where('growth_campaign_id', (int) $campaignId)
            ->where('key', $artifactKey ?? self::CONCEPT_KEY)
            ->first();

        GrowthDecision::query()->create([
            'growth_campaign_id' => (int) $campaignId,
            'growth_artifact_id' => $artifact?->id,
            'type' => $type,
            'status' => $status,
            'question' => $question,
            'decision' => $decision,
            'decided_by_user_id' => $actor->id,
            'decided_at' => now(),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    public function supersedeApprovedConcept(int $campaignId): void
    {
        GrowthDecision::query()
            ->where('growth_campaign_id', $campaignId)
            ->where('type', self::DECISION_CONCEPT_APPROVAL)
            ->where('status', GrowthDecision::STATUS_APPROVED)
            ->update(['status' => GrowthDecision::STATUS_SUPERSEDED]);
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public function persistMaterial(
        array $project,
        User $actor,
        string $materialId,
        string $source = GrowthArtifactVersion::SOURCE_MANUAL,
        ?int $restoredFromVersion = null,
    ): void {
        $campaignId = $project['growth_campaign_id'] ?? null;
        $materials = $project['materials'] ?? null;
        if (! is_numeric($campaignId) || ! is_array($materials)) {
            return;
        }

        $material = collect($materials)->first(
            fn (mixed $item): bool => is_array($item) && ($item['id'] ?? null) === $materialId,
        );
        if (! is_array($material)) {
            return;
        }

        $workspaceStatus = (string) ($material['status'] ?? 'DRAFT');
        $artifact = GrowthArtifact::query()->firstOrNew([
            'growth_campaign_id' => (int) $campaignId,
            'key' => $materialId,
        ]);

        $draft = (string) ($material['draft'] ?? '');
        $previousPayload = $artifact->exists && is_array($artifact->payload) ? $artifact->payload : null;
        $draftChanged = $previousPayload === null || (string) ($previousPayload['draft'] ?? '') !== $draft;
        $isMainMail = $materialId === MaterialDraftTask::MAIL_MATERIAL_KEY;
        $templateKey = $isMainMail
            ? MailTemplates::key($material['template_key'] ?? ($previousPayload['template_key'] ?? null))
            : null;
        $includePaidOffer = $isMainMail && (bool) ($material['include_paid_offer'] ?? false);
        $showCertificate = $isMainMail && (bool) ($material['show_certificate'] ?? false);
        $paidSnapshot = $isMainMail && is_array($material['paid_offer_snapshot'] ?? null)
            ? $material['paid_offer_snapshot']
            : null;
        $mailMetaChanged = $isMainMail && $previousPayload !== null && (
            MailTemplates::key($previousPayload['template_key'] ?? null) !== $templateKey
            || (bool) ($previousPayload['include_paid_offer'] ?? false) !== $includePaidOffer
            || (bool) ($previousPayload['show_certificate'] ?? false) !== $showCertificate
            || json_encode($previousPayload['paid_offer_snapshot'] ?? null) !== json_encode($paidSnapshot)
        );
        $changed = $draftChanged || $mailMetaChanged;

        DB::transaction(function () use ($artifact, $material, $workspaceStatus, $draft, $previousPayload, $changed, $templateKey, $isMainMail, $includePaidOffer, $showCertificate, $paidSnapshot, $actor, $source, $restoredFromVersion): void {
            if ($changed && $previousPayload !== null && ! $artifact->versions()->exists()) {
                $artifact->versions()->create([
                    'version' => (int) $artifact->version,
                    'source' => GrowthArtifactVersion::SOURCE_BASELINE,
                    'payload' => $previousPayload,
                    'created_by_user_id' => null,
                ]);
            }

            $artifact->type = self::MATERIAL_TYPE;
            $artifact->status = $this->artifactStatus($workspaceStatus);
            $artifact->title = mb_substr(trim((string) ($material['name'] ?? '')), 0, 180) ?: null;
            $artifact->summary = trim((string) ($material['summary'] ?? '')) ?: null;
            $artifact->schema_version = self::SCHEMA_VERSION;
            $artifact->version = $artifact->exists ? ((int) $artifact->version + 1) : 1;
            $payload = [
                'status' => $workspaceStatus,
                'draft' => $draft,
            ];
            if ($isMainMail) {
                $payload['template_key'] = $templateKey;
                $payload['include_paid_offer'] = $includePaidOffer;
                $payload['show_certificate'] = $showCertificate;
                if (is_array($paidSnapshot)) {
                    $payload['paid_offer_snapshot'] = $paidSnapshot;
                }
            }

            $origin = DemoTikWebinarProject::normalizeAiOrigin($material['ai_origin'] ?? null);
            if ($origin === null && is_array($previousPayload['ai_origin'] ?? null)) {
                $origin = DemoTikWebinarProject::normalizeAiOrigin($previousPayload['ai_origin']);
            }
            if ($origin !== null) {
                $payload['ai_origin'] = $origin;
            }

            $artifact->payload = $payload;
            if (! $artifact->exists) {
                $artifact->created_by_user_id = $actor->id;
            }
            $artifact->save();

            if (! $changed) {
                return;
            }

            $artifact->versions()->create([
                'version' => (int) $artifact->version,
                'source' => $source,
                'restored_from_version' => $source === GrowthArtifactVersion::SOURCE_RESTORE ? $restoredFromVersion : null,
                'payload' => $artifact->payload,
                'created_by_user_id' => $actor->id,
            ]);

            $staleIds = $artifact->versions()
                ->orderByDesc('version')
                ->skip(GrowthArtifactVersion::KEEP_LATEST)
                ->take(PHP_INT_MAX)
                ->pluck('id');
            if ($staleIds->isNotEmpty()) {
                GrowthArtifactVersion::query()->whereIn('id', $staleIds)->delete();
            }
        });
    }

    /**
     * @return Collection<int, GrowthArtifactVersion>
     */
    public function materialVersions(int $campaignId, string $materialId): Collection
    {
        $artifact = $this->materialArtifact($campaignId, $materialId);
        if ($artifact === null) {
            return new Collection;
        }

        return $artifact->versions()->with('createdBy:id,name')->orderByDesc('version')->get();
    }

    public function materialVersion(int $campaignId, string $materialId, int $version): ?GrowthArtifactVersion
    {
        $artifact = $this->materialArtifact($campaignId, $materialId);
        if ($artifact === null) {
            return null;
        }

        $row = $artifact->versions()->where('version', $version)->first();

        return $row instanceof GrowthArtifactVersion ? $row : null;
    }

    /**
     * Images need a database artifact; a material that was never saved is persisted first.
     *
     * @param  array<string, mixed>  $project
     */
    public function ensureMaterialArtifact(array $project, User $actor, string $materialId): ?GrowthArtifact
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return null;
        }

        $artifact = $this->materialArtifact((int) $campaignId, $materialId);
        if ($artifact !== null) {
            return $artifact;
        }

        $this->persistMaterial($project, $actor, $materialId);

        return $this->materialArtifact((int) $campaignId, $materialId);
    }

    /**
     * @return Collection<int, GrowthArtifactImage>
     */
    public function materialImages(int $campaignId, string $materialId): Collection
    {
        $artifact = $this->materialArtifact($campaignId, $materialId);
        if ($artifact === null) {
            return new Collection;
        }

        return $artifact->images()->with('createdBy:id,name')->orderByDesc('id')->get();
    }

    public function materialImage(int $campaignId, string $materialId, int $imageId): ?GrowthArtifactImage
    {
        $artifact = $this->materialArtifact($campaignId, $materialId);
        if ($artifact === null) {
            return null;
        }

        $image = $artifact->images()->whereKey($imageId)->first();

        return $image instanceof GrowthArtifactImage ? $image : null;
    }

    private function materialArtifact(int $campaignId, string $materialId): ?GrowthArtifact
    {
        $artifact = GrowthArtifact::query()
            ->where('growth_campaign_id', $campaignId)
            ->where('key', $materialId)
            ->where('type', self::MATERIAL_TYPE)
            ->first();

        return $artifact instanceof GrowthArtifact ? $artifact : null;
    }

    public function supersedeApprovedDirection(int $campaignId): void
    {
        GrowthDecision::query()
            ->where('growth_campaign_id', $campaignId)
            ->where('type', self::DECISION_DIRECTION_APPROVAL)
            ->where('status', GrowthDecision::STATUS_APPROVED)
            ->update(['status' => GrowthDecision::STATUS_SUPERSEDED]);
    }

    public function latestDirectionApproval(int $campaignId): ?GrowthDecision
    {
        $decision = GrowthDecision::query()
            ->where('growth_campaign_id', $campaignId)
            ->where('type', self::DECISION_DIRECTION_APPROVAL)
            ->latest('id')
            ->first();

        return $decision instanceof GrowthDecision ? $decision : null;
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public function persistDirection(array $project, User $actor): void
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        $direction = $project['direction'] ?? null;
        if (! is_numeric($campaignId) || ! is_array($direction)) {
            return;
        }

        $artifact = GrowthArtifact::query()->firstOrNew([
            'growth_campaign_id' => (int) $campaignId,
            'key' => self::DIRECTION_KEY,
        ]);

        $artifact->type = self::DIRECTION_TYPE;
        $artifact->status = GrowthArtifact::STATUS_DRAFT;
        $artifact->title = 'Kierunek';
        $artifact->schema_version = self::SCHEMA_VERSION;
        $artifact->version = $artifact->exists ? ((int) $artifact->version + 1) : 1;
        $payload = [
            'why_now' => trim((string) ($direction['why_now'] ?? '')),
            'audience' => trim((string) ($direction['audience'] ?? '')),
            'problem' => trim((string) ($direction['problem'] ?? '')),
            'takeaway' => trim((string) ($direction['takeaway'] ?? '')),
            'sell_later' => trim((string) ($direction['sell_later'] ?? '')),
        ];

        $origin = DemoTikWebinarProject::normalizeAiOrigin($project['direction_ai_origin'] ?? null);
        if ($origin === null && $artifact->exists && is_array($artifact->payload['ai_origin'] ?? null)) {
            $origin = DemoTikWebinarProject::normalizeAiOrigin($artifact->payload['ai_origin']);
        }
        if ($origin !== null) {
            $payload['ai_origin'] = $origin;
        }

        $artifact->payload = $payload;
        if (! $artifact->exists) {
            $artifact->created_by_user_id = $actor->id;
        }
        $artifact->save();
    }

    public function latestConceptApproval(int $campaignId): ?GrowthDecision
    {
        $decision = GrowthDecision::query()
            ->where('growth_campaign_id', $campaignId)
            ->where('type', self::DECISION_CONCEPT_APPROVAL)
            ->latest('id')
            ->first();

        return $decision instanceof GrowthDecision ? $decision : null;
    }

    private function instructorId(mixed $id): ?int
    {
        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    private function hostName(string $host): ?string
    {
        $host = mb_substr(trim($host), 0, 120);

        return $host !== '' ? $host : null;
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

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    private function overlayDirection(array $project, int $campaignId): array
    {
        $artifact = GrowthArtifact::query()
            ->where('growth_campaign_id', $campaignId)
            ->where('key', self::DIRECTION_KEY)
            ->first();

        if (! $artifact instanceof GrowthArtifact || ! is_array($artifact->payload)) {
            return $project;
        }

        $direction = $project['direction'] ?? [];
        if (! is_array($direction)) {
            $direction = [];
        }

        foreach (['why_now', 'audience', 'problem', 'takeaway', 'sell_later'] as $field) {
            if (is_string($artifact->payload[$field] ?? null)) {
                $direction[$field] = $artifact->payload[$field];
            }
        }

        $project['direction'] = $direction;

        $origin = DemoTikWebinarProject::normalizeAiOrigin($artifact->payload['ai_origin'] ?? null);
        if ($origin !== null) {
            $project['direction_ai_origin'] = $origin;
        }

        return $project;
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    private function overlayMaterials(array $project, int $campaignId): array
    {
        $materials = $project['materials'] ?? null;
        if (! is_array($materials)) {
            return $project;
        }

        $artifacts = GrowthArtifact::query()
            ->where('growth_campaign_id', $campaignId)
            ->where('type', self::MATERIAL_TYPE)
            ->get()
            ->keyBy('key');

        foreach ($materials as $index => $material) {
            if (! is_array($material)) {
                continue;
            }

            $artifact = $artifacts->get($material['id'] ?? null);
            if (! $artifact instanceof GrowthArtifact || ! is_array($artifact->payload)) {
                continue;
            }

            $status = $artifact->payload['status'] ?? null;
            if (is_string($status) && array_key_exists($status, DemoTikWebinarProject::materialStatusLabels())) {
                $materials[$index]['status'] = $status;
            }
            if (is_string($artifact->payload['draft'] ?? null)) {
                $materials[$index]['draft'] = $artifact->payload['draft'];
            }
            if (($material['id'] ?? null) === MaterialDraftTask::MAIL_MATERIAL_KEY) {
                if (is_string($artifact->payload['template_key'] ?? null)) {
                    $materials[$index]['template_key'] = MailTemplates::key($artifact->payload['template_key']);
                }
                $materials[$index]['include_paid_offer'] = (bool) ($artifact->payload['include_paid_offer'] ?? false);
                $materials[$index]['show_certificate'] = (bool) ($artifact->payload['show_certificate'] ?? false);
                if (is_array($artifact->payload['paid_offer_snapshot'] ?? null)) {
                    $materials[$index]['paid_offer_snapshot'] = $artifact->payload['paid_offer_snapshot'];
                }
            }

            $origin = DemoTikWebinarProject::normalizeAiOrigin($artifact->payload['ai_origin'] ?? null);
            if ($origin !== null) {
                $materials[$index]['ai_origin'] = $origin;
            }
        }

        $project['materials'] = $materials;

        return $project;
    }

    private function artifactStatus(string $workspaceStatus): string
    {
        return match ($workspaceStatus) {
            'NOT_STARTED' => GrowthArtifact::STATUS_NOT_STARTED,
            'REVIEW' => GrowthArtifact::STATUS_REVIEW,
            'APPROVED', 'PUBLISHED' => GrowthArtifact::STATUS_APPROVED,
            DemoTikWebinarProject::MATERIAL_SKIPPED => GrowthArtifact::STATUS_ARCHIVED,
            default => GrowthArtifact::STATUS_DRAFT,
        };
    }
}
