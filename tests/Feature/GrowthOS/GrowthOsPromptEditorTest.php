<?php

namespace Tests\Feature\GrowthOS;

use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\AI\Tasks\DirectionPlanningTask;
use App\Services\GrowthOS\AI\Tasks\GraphicImageTask;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthOsPromptEditorTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();
        config()->set('growth_os.enabled', true);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_saved_prompt_replaces_the_code_version_for_the_api_and_can_be_restored(): void
    {
        $user = $this->readyProject();
        $custom = 'Jesteś redaktorem testowym PNE. Zwróć wyłącznie dane zgodne ze schematem.';
        $generate = new DirectionPlanningTask(DirectionPlanningTask::MODE_GENERATE, true);
        $untouched = new DirectionPlanningTask(DirectionPlanningTask::MODE_GENERATE, false);
        $default = $generate->defaultInstructions();
        $other = $untouched->instructions();

        $this->actingAs($user)
            ->from(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->put(route('growth.prompts.update', 'direction-generate-search'), ['body' => $custom])
            ->assertRedirect(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID).'#prompt-direction-generate-search')
            ->assertSessionHas('success');

        $this->assertSame($custom, (new DirectionPlanningTask(DirectionPlanningTask::MODE_GENERATE, true))->instructions());
        $this->assertSame($other, (new DirectionPlanningTask(DirectionPlanningTask::MODE_GENERATE, false))->instructions());

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Zapisz ten prompt')
            ->assertSee('Własna treść')
            ->assertSee($custom);

        $this->actingAs($user)
            ->from(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->delete(route('growth.prompts.reset', 'direction-generate-search'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($default, (new DirectionPlanningTask(DirectionPlanningTask::MODE_GENERATE, true))->instructions());
    }

    public function test_saved_image_template_is_filled_with_the_card_values(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->put(route('growth.prompts.update', 'image-landscape'), [
                'body' => "TYLKO TEST\n\n{{opis}}",
            ])
            ->assertRedirect();

        $prompt = (new GraphicImageTask)->prompt('Opis sceny od właściciela.', 'landscape', false, '', '', '2048x1152');

        $this->assertStringContainsString('TYLKO TEST', $prompt);
        $this->assertStringContainsString('Opis sceny od właściciela.', $prompt);
    }

    public function test_guest_cannot_replace_a_prompt(): void
    {
        $this->put(route('growth.prompts.update', 'direction-generate-search'), ['body' => 'Obca treść promptu.'])
            ->assertRedirect(route('login'));
    }

    private function readyProject(): User
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => 'Canva AI w pracy nauczyciela',
        ])->assertRedirect();

        return $user;
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
            ],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
