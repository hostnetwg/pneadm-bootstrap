<?php

namespace Tests\Feature\GrowthOS;

use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthCampaign;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\GrowthSessionConceptStore;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthOsConceptPersistenceTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_creating_project_stores_campaign_without_concept_artifact(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->post(route('growth.projects.store'), $this->projectPayload())
            ->assertRedirect(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID));

        $campaign = GrowthCampaign::query()->first();
        $this->assertNotNull($campaign);
        $this->assertSame($user->id, $campaign->owner_user_id);
        $this->assertNull($campaign->primary_instructor_id);
        $this->assertSame('Canva AI w pracy nauczyciela', $campaign->working_topic);
        $this->assertSame(GrowthCampaign::STATUS_PLANNING, $campaign->status);
        $this->assertSame(0, GrowthArtifact::query()->count());

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Waldemar Grabowski');
    }

    public function test_manual_save_and_apply_write_concept_while_reject_does_not(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());

        $this->actingAs($user)
            ->put(route('growth.projects.concept.update', DemoTikWebinarProject::PROJECT_ID), $this->conceptPayload('Tytuł zapisany'))
            ->assertRedirect();

        $artifact = GrowthArtifact::query()->first();
        $this->assertNotNull($artifact);
        $this->assertSame(GrowthSessionConceptStore::CONCEPT_KEY, $artifact->key);
        $this->assertSame(1, $artifact->version);
        $this->assertSame('Tytuł zapisany', $artifact->payload['title']);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'shorter',
            ]);

        $this->assertSame(1, $artifact->fresh()->version);
        $this->assertSame('Tytuł zapisany', $artifact->fresh()->payload['title']);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai.reject', DemoTikWebinarProject::PROJECT_ID));

        $this->assertSame(1, $artifact->fresh()->version);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'shorter',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai.apply', DemoTikWebinarProject::PROJECT_ID))
            ->assertRedirect();

        $artifact->refresh();
        $this->assertSame(2, $artifact->version);
        $this->assertNotSame('Tytuł zapisany', $artifact->payload['title']);
        $this->assertSame($artifact->payload['title'], DemoTikWebinarProject::project()['concept']['title']);
    }

    public function test_another_browser_restores_campaign_and_concept_for_the_owner_only(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());
        $this->actingAs($user)
            ->put(route('growth.projects.concept.update', DemoTikWebinarProject::PROJECT_ID), $this->conceptPayload('Tytuł z bazy'));
        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, 'facebook-post']), [
                'status' => 'APPROVED',
            ]);

        $stored = session(DemoTikWebinarProject::SESSION_PROJECT);
        $this->assertIsArray($stored);
        $stored['concept']['title'] = 'Tytuł podszyty w sesji';
        session([DemoTikWebinarProject::SESSION_PROJECT => $stored]);

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Tytuł z bazy')
            ->assertDontSee('Tytuł podszyty w sesji');

        session()->forget(DemoTikWebinarProject::SESSION_PROJECT);

        $this->actingAs($user)
            ->get(route('growth.dashboard'))
            ->assertOk()
            ->assertSee('Canva AI w pracy nauczyciela');

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Tytuł z bazy')
            ->assertDontSee('Waldemar Grabowski');

        $material = DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, 'facebook-post');
        $this->assertSame('DRAFT', $material['status']);

        session()->forget(DemoTikWebinarProject::SESSION_PROJECT);

        $this->actingAs($this->superAdmin())
            ->get(route('growth.dashboard'))
            ->assertOk()
            ->assertDontSee('Tytuł z bazy')
            ->assertDontSee('Canva AI w pracy nauczyciela');
    }

    public function test_concept_decisions_are_stored_and_return_after_a_new_session(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());

        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']));

        $this->assertSame(0, GrowthDecision::query()->count());

        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'concept']));

        $approval = GrowthDecision::query()->first();
        $this->assertNotNull($approval);
        $this->assertSame(GrowthSessionConceptStore::DECISION_CONCEPT_APPROVAL, $approval->type);
        $this->assertSame(GrowthDecision::STATUS_APPROVED, $approval->status);
        $this->assertSame($user->id, $approval->decided_by_user_id);

        session()->forget(DemoTikWebinarProject::SESSION_PROJECT);

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Koncepcja oznaczona jako gotowa.')
            ->assertSee('Cofnij zatwierdzenie');

        $this->actingAs($user)
            ->post(route('growth.projects.steps.reopen', [DemoTikWebinarProject::PROJECT_ID, 'concept']));

        $this->assertSame(
            GrowthDecision::STATUS_SUPERSEDED,
            $approval->fresh()->status,
        );
        $this->assertSame(1, GrowthDecision::query()->where('status', GrowthDecision::STATUS_CHANGES_REQUESTED)->count());

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'shorter',
            ]);
        $before = GrowthArtifact::query()->count();
        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai.reject', DemoTikWebinarProject::PROJECT_ID));

        $this->assertSame($before, GrowthArtifact::query()->count());
        $this->assertSame(1, GrowthDecision::query()->where('type', GrowthSessionConceptStore::DECISION_CONCEPT_AI_REJECT)->count());
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

    /**
     * @return array<string, string>
     */
    private function conceptPayload(string $title): array
    {
        return [
            'title' => $title,
            'subtitle' => 'Podtytuł testowy',
            'promise' => 'Obietnica testowa',
            'points' => "Punkt 1\nPunkt 2",
            'plan' => 'Plan testowy',
            'cta' => 'CTA testowe',
            'lead_magnet' => 'Lead magnet',
            'next_product' => 'nie',
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
