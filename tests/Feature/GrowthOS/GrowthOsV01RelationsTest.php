<?php

namespace Tests\Feature\GrowthOS;

use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthCampaign;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\GrowthOS\GrowthTask;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthOsV01RelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_graph_links_owner_instructor_artifact_task_and_decision(): void
    {
        $owner = User::factory()->create();
        $instructor = Instructor::query()->create([
            'first_name' => 'Roman',
            'last_name' => 'Lorens',
            'email' => 'roman.relations@example.test',
            'is_active' => true,
        ]);

        $campaign = GrowthCampaign::query()->create([
            'name' => 'Webinar TIK',
            'type' => 'webinar',
            'status' => GrowthCampaign::STATUS_PLANNING,
            'goal' => 'education',
            'owner_user_id' => $owner->id,
            'primary_instructor_id' => $instructor->id,
            'working_topic' => 'Canva AI w pracy nauczyciela',
        ]);

        $artifact = $campaign->artifacts()->create([
            'key' => 'concept',
            'type' => 'concept',
            'status' => GrowthArtifact::STATUS_DRAFT,
            'schema_version' => 1,
            'version' => 1,
            'payload' => ['title' => 'Canva AI'],
            'created_by_user_id' => $owner->id,
        ]);

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
            'status' => GrowthDecision::STATUS_APPROVED,
            'decision' => 'Koncepcja przyjęta.',
            'decided_by_user_id' => $owner->id,
            'decided_at' => now(),
            'meta' => ['source' => 'human'],
        ]);

        $campaign->refresh();

        $this->assertTrue($campaign->owner->is($owner));
        $this->assertTrue($campaign->primaryInstructor->is($instructor));
        $this->assertTrue($owner->ownedGrowthCampaigns->first()->is($campaign));
        $this->assertTrue($instructor->primaryGrowthCampaigns->first()->is($campaign));
        $this->assertTrue($artifact->campaign->is($campaign));
        $this->assertTrue($artifact->createdBy->is($owner));
        $this->assertTrue($artifact->tasks->first()->is($task));
        $this->assertTrue($task->artifact->is($artifact));
        $this->assertTrue($task->assignee->is($owner));
        $this->assertTrue($decision->campaign->is($campaign));
        $this->assertTrue($decision->artifact->is($artifact));
        $this->assertTrue($decision->task->is($task));
        $this->assertTrue($decision->decidedBy->is($owner));
        $this->assertSame('Canva AI', $artifact->payload['title']);
        $this->assertSame('human', $decision->meta['source']);
    }
}
