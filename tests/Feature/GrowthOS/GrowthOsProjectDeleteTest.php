<?php

namespace Tests\Feature\GrowthOS;

use App\Http\Controllers\GrowthOS\ProjectController;
use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthArtifactImage;
use App\Models\GrowthOS\GrowthCampaign;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\GrowthOS\GrowthTask;
use App\Models\Role;
use App\Models\User;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GrowthOsProjectDeleteTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();
        config()->set('growth_os.enabled', true);
        config()->set('growth_ai.enabled', false);
        config()->set('growth_ai.images.disk', 'local');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_index_lists_all_owned_campaigns_and_not_other_users(): void
    {
        $user = $this->superAdmin();
        $other = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload('NotebookLM w pracy nauczyciela'));
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload('Drugi webinar testowy'));
        $this->actingAs($other)->post(route('growth.projects.store'), $this->projectPayload('Cudzy webinar'));

        $this->actingAs($user)
            ->get(route('growth.projects.index'))
            ->assertOk()
            ->assertSee('NotebookLM w pracy nauczyciela')
            ->assertSee('Drugi webinar testowy')
            ->assertDontSee('Cudzy webinar')
            ->assertSee('Usuń')
            ->assertSee('Usunąć projekt?')
            ->assertSee('nie da się cofnąć')
            ->assertDontSee('confirm(');

        $this->assertSame(3, GrowthCampaign::query()->count());
    }

    public function test_opening_an_older_campaign_loads_it_into_the_workspace(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload('Starszy temat'));
        $older = GrowthCampaign::query()->sole();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload('Nowszy temat'));

        $this->assertSame('Nowszy temat', DemoTikWebinarProject::project()['topic']);

        $this->actingAs($user)
            ->post(route('growth.projects.open', $older->id))
            ->assertRedirect(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID));

        $this->assertSame('Starszy temat', DemoTikWebinarProject::project()['topic']);
        $this->assertSame($older->id, DemoTikWebinarProject::project()['growth_campaign_id']);
    }

    public function test_deleting_the_open_project_removes_children_files_and_session(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload('Do usunięcia'));
        $this->actingAs($user)->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']));
        $this->actingAs($user)->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, 'landing']), [
            'status' => 'DRAFT',
            'draft' => 'Szkic landing',
        ]);

        $campaign = GrowthCampaign::query()->sole();
        $artifact = GrowthArtifact::query()->where('key', 'landing')->sole();
        $path = 'growth-os/images/'.$artifact->id.'/test.jpg';
        Storage::disk('local')->put($path, 'fake-image');
        GrowthArtifactImage::query()->create([
            'growth_artifact_id' => $artifact->id,
            'format' => 'square',
            'width' => 1080,
            'height' => 1080,
            'disk' => 'local',
            'path' => $path,
            'mime' => 'image/jpeg',
            'size_bytes' => 10,
            'prompt' => 'test',
            'include_headline' => false,
            'source' => GrowthArtifactImage::SOURCE_SIMULATION,
            'prompt_version' => 'test',
            'is_selected' => false,
            'created_by_user_id' => $user->id,
        ]);

        $this->assertNotNull(DemoTikWebinarProject::project());

        $this->actingAs($user)
            ->delete(route('growth.projects.destroy', $campaign->id))
            ->assertRedirect(route('growth.projects.index'))
            ->assertSessionHas('success', ProjectController::PROJECT_DELETED_MESSAGE);

        $this->assertSame(0, GrowthCampaign::query()->count());
        $this->assertSame(0, GrowthArtifact::query()->count());
        $this->assertSame(0, GrowthTask::query()->count());
        $this->assertSame(0, GrowthDecision::query()->count());
        $this->assertSame(0, GrowthArtifactImage::query()->count());
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertNull(session(DemoTikWebinarProject::SESSION_PROJECT));
        $this->assertNull(DemoTikWebinarProject::project());

        $this->actingAs($user)
            ->get(route('growth.projects.index'))
            ->assertOk()
            ->assertSee('Brak projektu webinaru')
            ->assertDontSee('Do usunięcia');
    }

    public function test_deleting_one_campaign_leaves_the_other(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload('Zostaje'));
        $keep = GrowthCampaign::query()->sole();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload('Do skasowania'));
        $remove = GrowthCampaign::query()->whereKeyNot($keep->id)->sole();

        $this->actingAs($user)
            ->delete(route('growth.projects.destroy', $remove->id))
            ->assertRedirect(route('growth.projects.index'));

        $this->assertSame(1, GrowthCampaign::query()->count());
        $this->assertNotNull(GrowthCampaign::query()->find($keep->id));
        $this->actingAs($user)
            ->get(route('growth.projects.index'))
            ->assertSee('Zostaje')
            ->assertDontSee('Do skasowania');
    }

    public function test_cannot_delete_another_users_campaign(): void
    {
        $owner = $this->superAdmin();
        $other = $this->superAdmin();
        $this->actingAs($owner)->post(route('growth.projects.store'), $this->projectPayload('Cudzy projekt'));
        $campaign = GrowthCampaign::query()->sole();

        $this->actingAs($other)
            ->delete(route('growth.projects.destroy', $campaign->id))
            ->assertNotFound();

        $this->assertNotNull(GrowthCampaign::query()->find($campaign->id));
    }

    public function test_cannot_open_another_users_campaign(): void
    {
        $owner = $this->superAdmin();
        $other = $this->superAdmin();
        $this->actingAs($owner)->post(route('growth.projects.store'), $this->projectPayload('Cudzy projekt'));
        $campaign = GrowthCampaign::query()->sole();

        $this->actingAs($other)
            ->post(route('growth.projects.open', $campaign->id))
            ->assertNotFound();
    }

    /**
     * @return array<string, string>
     */
    private function projectPayload(string $topic): array
    {
        return [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => $topic,
        ];
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'super_admin'],
            [
                'display_name' => 'super_admin',
                'description' => 'Rola testowa Growth OS',
                'is_system' => true,
                'level' => 100,
            ]
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
