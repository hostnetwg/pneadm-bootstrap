<?php

namespace Tests\Feature\GrowthOS;

use App\Models\Role;
use App\Models\User;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthOsStage02PrototypeTest extends TestCase
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

    public function test_dashboard_invites_to_plan_tik_webinar_when_session_has_no_project(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('growth.dashboard'))
            ->assertOk()
            ->assertSee('Dzisiaj')
            ->assertSee('Zaplanuj webinar TIK')
            ->assertSee('Etap 0.3.1');
    }

    public function test_start_form_creates_session_project_and_redirects_to_workspace(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->post(route('growth.projects.store'), $this->projectPayload())
            ->assertRedirect(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID));

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Najważniejszy następny krok')
            ->assertSee('Pomysł i kierunek')
            ->assertSee('Checklista czasowa')
            ->assertSee('Materiały');
    }

    public function test_workspace_steps_change_only_session_state(): void
    {
        $user = $this->superAdmin();
        $this->createSessionProject($user);

        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']))
            ->assertRedirect(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID).'#direction');

        $project = DemoTikWebinarProject::project();
        $this->assertIsArray($project);
        $this->assertArrayHasKey('direction', $project['completed_steps']);

        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'concept']))
            ->assertRedirect(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID).'#concept');

        $project = DemoTikWebinarProject::project();
        $this->assertSame('PREPARING', $project['status']);
        $this->assertArrayHasKey('concept', $project['completed_steps']);
    }

    public function test_material_detail_updates_status_without_publication(): void
    {
        $user = $this->superAdmin();
        $this->createSessionProject($user);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description']))
            ->assertOk()
            ->assertSee('Draft / sugestia AI')
            ->assertSee('nic nie jest publikowane', false);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description']), [
                'status' => 'APPROVED',
            ])
            ->assertRedirect(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description']));

        $material = DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, 'youtube-description');
        $this->assertSame('APPROVED', $material['status']);
    }

    public function test_inbox_links_to_project_context(): void
    {
        $user = $this->superAdmin();
        $this->createSessionProject($user);

        $this->actingAs($user)
            ->get(route('growth.inbox.index'))
            ->assertOk()
            ->assertSee('Pomysł i kierunek webinaru')
            ->assertSee('Przejdź do miejsca w projekcie')
            ->assertSee(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID).'#direction', false);
    }

    public function test_unknown_project_or_material_returns_not_found(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('growth.projects.show', 'missing-project'))
            ->assertNotFound();

        $user = $this->superAdmin();
        $this->createSessionProject($user);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, 'missing-material']))
            ->assertNotFound();
    }

    public function test_concept_can_be_edited_reopened_and_ai_proposal_stays_optional(): void
    {
        $user = $this->superAdmin();
        $this->createSessionProject($user);

        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']));

        $this->actingAs($user)
            ->put(route('growth.projects.concept.update', DemoTikWebinarProject::PROJECT_ID), [
                'title' => 'Canva AI — nowy tytuł',
                'subtitle' => 'Podtytuł testowy',
                'promise' => 'Obietnica testowa',
                'points' => "Punkt 1\nPunkt 2\nPunkt 3",
                'plan' => 'Plan testowy',
                'cta' => 'CTA testowe',
                'lead_magnet' => 'Lead magnet',
                'next_product' => 'nie',
            ])
            ->assertRedirect();

        $project = DemoTikWebinarProject::project();
        $this->assertSame('Canva AI — nowy tytuł', $project['concept']['title']);
        $this->assertNotEmpty($project['concept_versions']);

        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'concept']));

        $this->assertArrayHasKey('concept', DemoTikWebinarProject::project()['completed_steps']);

        $this->actingAs($user)
            ->post(route('growth.projects.steps.reopen', [DemoTikWebinarProject::PROJECT_ID, 'concept']))
            ->assertRedirect();

        $this->assertArrayNotHasKey('concept', DemoTikWebinarProject::project()['completed_steps'] ?? []);

        $beforeTitle = DemoTikWebinarProject::project()['concept']['title'];

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'shorter',
            ])
            ->assertRedirect();

        $project = DemoTikWebinarProject::project();
        $this->assertSame($beforeTitle, $project['concept']['title']);
        $this->assertIsArray($project['concept_ai_proposal']);
        $this->assertNotSame($beforeTitle, $project['concept_ai_proposal']['concept']['title']);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai.apply', DemoTikWebinarProject::PROJECT_ID))
            ->assertRedirect();

        $project = DemoTikWebinarProject::project();
        $this->assertNull($project['concept_ai_proposal']);
        $this->assertNotSame($beforeTitle, $project['concept']['title']);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'practical',
            ]);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai.reject', DemoTikWebinarProject::PROJECT_ID))
            ->assertRedirect();

        $this->assertNull(DemoTikWebinarProject::project()['concept_ai_proposal']);
    }

    private function createSessionProject(User $user): void
    {
        $this->actingAs($user)
            ->post(route('growth.projects.store'), $this->projectPayload());
    }

    /**
     * @return array<string, string>
     */
    private function projectPayload(): array
    {
        return [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => 'Canva AI w pracy nauczyciela',
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
