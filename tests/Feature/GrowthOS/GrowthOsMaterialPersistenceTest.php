<?php

namespace Tests\Feature\GrowthOS;

use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\GrowthSessionConceptStore;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthOsMaterialPersistenceTest extends TestCase
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

    public function test_opening_a_material_does_not_create_an_artifact(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, 'landing']))
            ->assertOk()
            ->assertSee('Zapisz materiał');

        $this->assertSame(0, GrowthArtifact::query()->count());
    }

    public function test_saving_all_materials_persists_status_and_draft_without_a_decision(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());

        foreach ($this->materialKeys() as $key) {
            $this->actingAs($user)
                ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, $key]), [
                    'status' => 'REVIEW',
                    'draft' => 'Szkic '.$key,
                ])
                ->assertRedirect();
        }

        $this->assertSame(10, GrowthArtifact::query()->count());
        $this->assertSame(0, GrowthDecision::query()->count());

        $landing = GrowthArtifact::query()->where('key', 'landing')->first();
        $this->assertNotNull($landing);
        $this->assertSame(GrowthSessionConceptStore::MATERIAL_TYPE, $landing->type);
        $this->assertSame(GrowthSessionConceptStore::SCHEMA_VERSION, $landing->schema_version);
        $this->assertSame(GrowthArtifact::STATUS_REVIEW, $landing->status);
        $this->assertSame('REVIEW', $landing->payload['status']);
        $this->assertSame('Szkic landing', $landing->payload['draft']);
        $this->assertSame(1, $landing->version);
        $this->assertNull($landing->tasks()->first());

        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, 'landing']), [
                'status' => 'APPROVED',
                'draft' => 'Szkic landing v2',
            ]);

        $landing->refresh();
        $this->assertSame(2, $landing->version);
        $this->assertSame(GrowthArtifact::STATUS_APPROVED, $landing->status);
        $this->assertSame('Szkic landing v2', $landing->payload['draft']);

        $project = DemoTikWebinarProject::project();
        $this->assertIsArray($project);
        $this->assertSame(
            'Następny krok: zatwierdź kierunek',
            DemoTikWebinarProject::nextAction($project)['label'],
        );
    }

    public function test_published_label_stays_in_the_payload_and_maps_to_approved(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());

        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, 'facebook-post']), [
                'status' => 'PUBLISHED',
                'draft' => 'Post zapisany',
            ]);

        $artifact = GrowthArtifact::query()->where('key', 'facebook-post')->first();
        $this->assertNotNull($artifact);
        $this->assertSame(GrowthArtifact::STATUS_APPROVED, $artifact->status);
        $this->assertSame('PUBLISHED', $artifact->payload['status']);
        $this->assertNotContains('published', GrowthArtifact::STATUSES);
    }

    public function test_saved_materials_return_after_a_new_session_and_direction_does_not(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());

        foreach ($this->materialKeys() as $key) {
            $this->actingAs($user)
                ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, $key]), [
                    'status' => $key === 'host-script' ? 'APPROVED' : 'DRAFT',
                    'draft' => 'Trwały szkic '.$key,
                ]);
        }

        $stored = session(DemoTikWebinarProject::SESSION_PROJECT);
        $this->assertIsArray($stored);
        $stored['direction']['audience'] = 'Odbiorcy tylko w tej przeglądarce';
        $stored['materials'][0]['draft'] = 'Szkic podszyty w sesji';
        session([DemoTikWebinarProject::SESSION_PROJECT => $stored]);

        session()->forget(DemoTikWebinarProject::SESSION_PROJECT);

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertDontSee('Szkic podszyty w sesji')
            ->assertDontSee('Odbiorcy tylko w tej przeglądarce');

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description']))
            ->assertOk()
            ->assertSee('Trwały szkic youtube-description');

        foreach ($this->materialKeys() as $key) {
            $material = DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, $key);
            $this->assertSame('Trwały szkic '.$key, $material['draft']);
            $this->assertSame($key === 'host-script' ? 'APPROVED' : 'DRAFT', $material['status']);
        }

        $project = DemoTikWebinarProject::project();
        $this->assertIsArray($project);
        $this->assertSame('', $project['direction']['audience']);
    }

    /**
     * @return list<string>
     */
    private function materialKeys(): array
    {
        return [
            'youtube-description',
            'main-graphic',
            'facebook-post',
            'main-mail',
            'reminder-mail',
            'landing',
            'host-script',
            'participant-material',
            'obs-intro',
            'follow-up',
        ];
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
