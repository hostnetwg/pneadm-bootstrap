<?php

namespace Tests\Feature\GrowthOS;

use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthCampaign;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\GrowthOS\GrowthTask;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthOsV01IntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_artifact_key_is_unique_inside_campaign_and_reusable_in_another(): void
    {
        $owner = User::factory()->create();
        $first = $this->campaign($owner, 'Webinar TIK');
        $second = $this->campaign($owner, 'Drugi webinar');

        $first->artifacts()->create($this->conceptPayload());
        $second->artifacts()->create($this->conceptPayload());

        $this->assertSame(1, $first->artifacts()->count());
        $this->assertSame(1, $second->artifacts()->count());

        $rejected = false;

        try {
            $first->artifacts()->create($this->conceptPayload());
        } catch (QueryException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $this->assertSame(1, $first->artifacts()->count());
    }

    public function test_optional_relations_can_stay_empty(): void
    {
        $owner = User::factory()->create();
        $campaign = $this->campaign($owner, 'Webinar bez eksperta');

        $artifact = $campaign->artifacts()->create([
            'key' => 'concept',
            'type' => 'concept',
            'status' => GrowthArtifact::STATUS_NOT_STARTED,
        ]);
        $task = $campaign->tasks()->create([
            'title' => 'Ustalić termin',
            'status' => GrowthTask::STATUS_TODO,
        ]);
        $decision = $campaign->decisions()->create([
            'type' => 'concept_approval',
            'status' => GrowthDecision::STATUS_PENDING,
        ]);

        $artifact->refresh();

        $this->assertNull($campaign->primary_instructor_id);
        $this->assertNull($campaign->primaryInstructor);
        $this->assertNull($artifact->created_by_user_id);
        $this->assertNull($artifact->createdBy);
        $this->assertNull($artifact->payload);
        $this->assertSame(1, $artifact->schema_version);
        $this->assertSame(1, $artifact->version);
        $this->assertNull($task->growth_artifact_id);
        $this->assertNull($task->artifact);
        $this->assertNull($task->assignee_user_id);
        $this->assertNull($decision->growth_artifact_id);
        $this->assertNull($decision->growth_task_id);
        $this->assertNull($decision->decided_by_user_id);
        $this->assertNull($decision->decided_at);
        $this->assertNull($decision->meta);
    }

    public function test_deleting_campaign_removes_children_and_keeps_owner(): void
    {
        $owner = User::factory()->create();
        $campaign = $this->campaign($owner, 'Webinar do usunięcia');
        $artifact = $campaign->artifacts()->create($this->conceptPayload());
        $task = $campaign->tasks()->create([
            'growth_artifact_id' => $artifact->id,
            'title' => 'Dopracować obietnicę',
            'status' => GrowthTask::STATUS_TODO,
        ]);
        $campaign->decisions()->create([
            'growth_artifact_id' => $artifact->id,
            'growth_task_id' => $task->id,
            'type' => 'concept_approval',
            'status' => GrowthDecision::STATUS_APPROVED,
        ]);

        $campaign->delete();

        $this->assertSame(0, GrowthCampaign::query()->count());
        $this->assertSame(0, GrowthArtifact::query()->count());
        $this->assertSame(0, GrowthTask::query()->count());
        $this->assertSame(0, GrowthDecision::query()->count());
        $this->assertNotNull(User::query()->find($owner->id));
    }

    public function test_deleting_artifact_or_task_clears_references_without_deleting_the_campaign(): void
    {
        $owner = User::factory()->create();
        $campaign = $this->campaign($owner, 'Webinar TIK');
        $artifact = $campaign->artifacts()->create($this->conceptPayload());
        $task = $campaign->tasks()->create([
            'growth_artifact_id' => $artifact->id,
            'title' => 'Dopracować obietnicę',
            'status' => GrowthTask::STATUS_TODO,
            'assignee_user_id' => $owner->id,
        ]);
        $decision = $campaign->decisions()->create([
            'growth_artifact_id' => $artifact->id,
            'growth_task_id' => $task->id,
            'type' => 'concept_approval',
            'status' => GrowthDecision::STATUS_PENDING,
            'decided_by_user_id' => $owner->id,
        ]);

        $artifact->delete();
        $task->refresh();
        $decision->refresh();

        $this->assertNull($task->growth_artifact_id);
        $this->assertNull($decision->growth_artifact_id);
        $this->assertSame($task->id, $decision->growth_task_id);
        $this->assertNotNull(GrowthCampaign::query()->find($campaign->id));

        $task->delete();
        $decision->refresh();

        $this->assertNull($decision->growth_task_id);
        $this->assertNotNull(GrowthDecision::query()->find($decision->id));
        $this->assertNotNull(GrowthCampaign::query()->find($campaign->id));
    }

    public function test_instructor_delete_clears_link_and_owner_delete_is_blocked(): void
    {
        $owner = User::factory()->create();
        $instructor = Instructor::query()->create([
            'first_name' => 'Roman',
            'last_name' => 'Lorens',
            'email' => 'roman.integrity@example.test',
            'is_active' => true,
        ]);
        $campaign = $this->campaign($owner, 'Webinar TIK', $instructor->id);

        $instructor->forceDelete();
        $campaign->refresh();

        $this->assertNull($campaign->primary_instructor_id);

        $blocked = false;

        try {
            $owner->forceDelete();
        } catch (QueryException) {
            $blocked = true;
        }

        $this->assertTrue($blocked);
        $this->assertNotNull(GrowthCampaign::query()->find($campaign->id));
        $this->assertSame($owner->id, $campaign->fresh()->owner_user_id);
    }

    public function test_growth_tables_do_not_use_soft_deletes(): void
    {
        foreach ([GrowthCampaign::class, GrowthArtifact::class, GrowthTask::class, GrowthDecision::class] as $model) {
            $this->assertNotContains(SoftDeletes::class, class_uses_recursive($model));
        }
    }

    private function campaign(User $owner, string $name, ?int $instructorId = null): GrowthCampaign
    {
        return GrowthCampaign::query()->create([
            'name' => $name,
            'type' => 'webinar',
            'status' => GrowthCampaign::STATUS_PLANNING,
            'owner_user_id' => $owner->id,
            'primary_instructor_id' => $instructorId,
            'working_topic' => 'Canva AI w pracy nauczyciela',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function conceptPayload(): array
    {
        return [
            'key' => 'concept',
            'type' => 'concept',
            'status' => GrowthArtifact::STATUS_DRAFT,
            'schema_version' => 1,
            'version' => 1,
            'payload' => ['title' => 'Canva AI'],
        ];
    }
}
