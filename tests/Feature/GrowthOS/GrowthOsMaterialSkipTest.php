<?php

namespace Tests\Feature\GrowthOS;

use App\Http\Controllers\GrowthOS\ProjectController;
use App\Models\GrowthOS\GrowthArtifact;
use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\AI\Contracts\GrowthAiImageProvider;
use App\Services\GrowthOS\AI\Contracts\GrowthAiProvider;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GrowthOsMaterialSkipTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();
        config()->set('growth_os.enabled', true);
        config()->set('growth_ai.enabled', true);
        Http::preventStrayRequests();

        $this->app->bind(GrowthAiProvider::class, fn () => $this->fail('Text AI must not be called for a skipped material.'));
        $this->app->bind(GrowthAiImageProvider::class, fn () => $this->fail('Image AI must not be called for a skipped material.'));
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_skipped_material_keeps_its_place_and_is_greyed_out(): void
    {
        $user = $this->readyProject();

        $this->setStatus($user, 'obs-intro', DemoTikWebinarProject::MATERIAL_SKIPPED)
            ->assertSessionHas('success', 'Materiał wyłączony (Nie dotyczy). Nie liczy się do następnego kroku ani elementów krytycznych. Szkic został zachowany.');

        $ids = collect(DemoTikWebinarProject::requireProject(DemoTikWebinarProject::PROJECT_ID)['materials'])->pluck('id')->all();
        $this->assertSame('obs-intro', $ids[8]);

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('growth-material-skipped', false)
            ->assertSee('Nie dotyczy');

        $artifact = GrowthArtifact::query()->where('key', 'obs-intro')->sole();
        $this->assertSame(GrowthArtifact::STATUS_ARCHIVED, $artifact->status);
        $this->assertSame(DemoTikWebinarProject::MATERIAL_SKIPPED, $artifact->payload['status']);
    }

    public function test_skipped_materials_do_not_drive_next_step_or_critical_count(): void
    {
        $user = $this->readyProject();
        $this->setStatus($user, 'youtube-description', 'APPROVED');

        $this->showProject($user)
            ->assertSee('Następny krok: przygotuj Grafika główna')
            ->assertSee('3 elementy krytyczne nie są gotowe.');

        $this->setStatus($user, 'main-graphic', DemoTikWebinarProject::MATERIAL_SKIPPED);
        $this->setStatus($user, 'landing', DemoTikWebinarProject::MATERIAL_SKIPPED);

        $this->showProject($user)
            ->assertSee('Następny krok: przygotuj Post Facebook')
            ->assertDontSee('Następny krok: przygotuj Grafika główna')
            ->assertSee('2 elementy krytyczne nie są gotowe.');
    }

    public function test_skipped_material_blocks_editing_and_ai_and_keeps_the_draft(): void
    {
        $user = $this->readyProject();
        $before = DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, 'facebook-post')['draft'];
        $this->setStatus($user, 'facebook-post', DemoTikWebinarProject::MATERIAL_SKIPPED);

        $this->actingAs($user)
            ->get($this->materialUrl('facebook-post'))
            ->assertOk()
            ->assertSee('Ten materiał jest wyłączony w tym projekcie')
            ->assertSee('readonly', false);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, 'facebook-post']))
            ->assertSessionHas('error', ProjectController::MATERIAL_SKIPPED_MESSAGE);
        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, 'facebook-post']))
            ->assertSessionHas('error', ProjectController::MATERIAL_SKIPPED_MESSAGE);
        $this->actingAs($user)
            ->post(route('growth.projects.materials.versions.restore', [DemoTikWebinarProject::PROJECT_ID, 'facebook-post', 1]))
            ->assertSessionHas('error', ProjectController::MATERIAL_SKIPPED_MESSAGE);

        $this->actingAs($user)->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, 'facebook-post']), [
            'status' => DemoTikWebinarProject::MATERIAL_SKIPPED,
            'draft' => 'Próba nadpisania',
        ]);
        $this->assertSame($before, DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, 'facebook-post')['draft']);

        $this->setStatus($user, 'facebook-post', 'DRAFT')
            ->assertSessionHas('success', 'Materiał jest znowu aktywny. Szkic jest taki jak przed wyłączeniem.');
        $this->assertSame($before, DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, 'facebook-post')['draft']);
        $this->actingAs($user)
            ->get($this->materialUrl('facebook-post'))
            ->assertDontSee('Ten materiał jest wyłączony w tym projekcie');
    }

    public function test_skipped_main_graphic_blocks_image_generation(): void
    {
        $user = $this->readyProject();
        $this->setStatus($user, 'main-graphic', DemoTikWebinarProject::MATERIAL_SKIPPED);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.images.generate', [DemoTikWebinarProject::PROJECT_ID, 'main-graphic']), [
                'format' => 'landscape',
                'image_prompt' => 'Biurko nauczyciela.',
                'include_headline' => '0',
            ])
            ->assertSessionHas('error', ProjectController::MATERIAL_SKIPPED_MESSAGE);

        $this->assertDatabaseCount('growth_artifact_images', 0);
    }

    public function test_skipped_youtube_description_is_not_an_ai_source(): void
    {
        $user = $this->readyProject();
        $this->actingAs($user)->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description']), [
            'status' => 'APPROVED',
            'draft' => 'Opis YouTube zatwierdzony do testu źródła.',
        ]);
        $project = DemoTikWebinarProject::requireProject(DemoTikWebinarProject::PROJECT_ID);
        $this->assertNotSame('', DemoTikWebinarProject::approvedYoutubeDescription($project));

        $this->setStatus($user, 'youtube-description', DemoTikWebinarProject::MATERIAL_SKIPPED);

        $project = DemoTikWebinarProject::requireProject(DemoTikWebinarProject::PROJECT_ID);
        $this->assertSame('', DemoTikWebinarProject::approvedYoutubeDescription($project));
    }

    private function setStatus(User $user, string $material, string $status): TestResponse
    {
        return $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, $material]), [
                'status' => $status,
                'draft' => DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, $material)['draft'],
            ])
            ->assertRedirect();
    }

    private function showProject(User $user): TestResponse
    {
        return $this->actingAs($user)->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))->assertOk();
    }

    private function materialUrl(string $key): string
    {
        return route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, $key]);
    }

    private function readyProject(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'super_admin'],
            ['display_name' => 'super_admin', 'description' => 'Rola testowa Growth OS', 'is_system' => true, 'level' => 100],
        );
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user)->post(route('growth.projects.store'), [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => 'Canva AI w pracy nauczyciela',
        ]);
        foreach (['direction', 'concept'] as $step) {
            $this->actingAs($user)->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, $step]))->assertRedirect();
        }

        return $user;
    }
}
