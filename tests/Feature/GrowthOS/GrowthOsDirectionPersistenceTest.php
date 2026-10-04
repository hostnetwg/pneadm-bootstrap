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

class GrowthOsDirectionPersistenceTest extends TestCase
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

    public function test_opening_the_project_does_not_store_direction(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Zapisz kierunek')
            ->assertSee('Kierunek to szkic');

        $this->assertSame(0, GrowthArtifact::query()->where('key', GrowthSessionConceptStore::DIRECTION_KEY)->count());
        $this->assertSame(0, GrowthDecision::query()->count());
    }

    public function test_saving_direction_persists_fields_without_a_decision(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());

        $this->actingAs($user)
            ->put(route('growth.projects.direction.update', DemoTikWebinarProject::PROJECT_ID), $this->directionPayload())
            ->assertRedirect();

        $artifact = GrowthArtifact::query()->where('key', GrowthSessionConceptStore::DIRECTION_KEY)->first();
        $this->assertNotNull($artifact);
        $this->assertSame(GrowthSessionConceptStore::DIRECTION_TYPE, $artifact->type);
        $this->assertSame(1, $artifact->schema_version);
        $this->assertSame(1, $artifact->version);
        $this->assertSame('Dla nauczycieli wczesnoszkolnych', $artifact->payload['audience']);
        $this->assertSame('tak', $artifact->payload['sell_later']);
        $this->assertArrayNotHasKey('suggestion', $artifact->payload);
        $this->assertSame(0, GrowthDecision::query()->count());

        $this->actingAs($user)
            ->put(route('growth.projects.direction.update', DemoTikWebinarProject::PROJECT_ID), [
                ...$this->directionPayload(),
                'audience' => 'Dla dyrektorów',
            ]);

        $this->assertSame(2, $artifact->fresh()->version);
        $this->assertSame('Dla dyrektorów', $artifact->fresh()->payload['audience']);
    }

    public function test_approval_and_host_return_after_a_new_session(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());
        $this->actingAs($user)
            ->put(route('growth.projects.direction.update', DemoTikWebinarProject::PROJECT_ID), $this->directionPayload());
        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']));

        $approval = GrowthDecision::query()->where('type', GrowthSessionConceptStore::DECISION_DIRECTION_APPROVAL)->first();
        $this->assertNotNull($approval);
        $this->assertSame(GrowthDecision::STATUS_APPROVED, $approval->status);
        $this->assertSame($user->id, $approval->decided_by_user_id);

        $project = DemoTikWebinarProject::project();
        $this->assertIsArray($project);
        $this->assertSame(
            'Następny krok: dopracuj koncepcję',
            DemoTikWebinarProject::nextAction($project)['label'],
        );

        session()->forget(DemoTikWebinarProject::SESSION_PROJECT);

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Dla nauczycieli wczesnoszkolnych')
            ->assertSee('Kierunek zatwierdzony.')
            ->assertSee('Cofnij zatwierdzenie')
            ->assertSee('Waldemar Grabowski');

        $restored = DemoTikWebinarProject::project();
        $this->assertIsArray($restored);
        $this->assertSame('Waldemar Grabowski', $restored['host']);
        $this->assertArrayHasKey('direction', $restored['completed_steps']);
    }

    public function test_reopen_and_edit_supersede_the_direction_approval(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());
        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']));

        $approval = GrowthDecision::query()->first();
        $this->assertNotNull($approval);

        $this->actingAs($user)
            ->put(route('growth.projects.direction.update', DemoTikWebinarProject::PROJECT_ID), $this->directionPayload());

        $this->assertSame(GrowthDecision::STATUS_SUPERSEDED, $approval->fresh()->status);
        $this->assertSame(0, GrowthDecision::query()->where('status', GrowthDecision::STATUS_CHANGES_REQUESTED)->count());

        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']));
        $second = GrowthDecision::query()->where('status', GrowthDecision::STATUS_APPROVED)->first();
        $this->assertNotNull($second);

        $this->actingAs($user)
            ->post(route('growth.projects.steps.reopen', [DemoTikWebinarProject::PROJECT_ID, 'direction']));

        $this->assertSame(GrowthDecision::STATUS_SUPERSEDED, $second->fresh()->status);
        $this->assertSame(1, GrowthDecision::query()->where('status', GrowthDecision::STATUS_CHANGES_REQUESTED)->count());

        session()->forget(DemoTikWebinarProject::SESSION_PROJECT);

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Zatwierdź kierunek')
            ->assertSee('Dla nauczycieli wczesnoszkolnych');

        $project = DemoTikWebinarProject::project();
        $this->assertIsArray($project);
        $this->assertArrayNotHasKey('direction', $project['completed_steps']);
        $this->assertSame(
            'Następny krok: zatwierdź kierunek',
            DemoTikWebinarProject::nextAction($project)['label'],
        );
    }

    /**
     * @return array<string, string>
     */
    private function directionPayload(): array
    {
        return [
            'why_now' => 'Nauczyciele szukają prostego przykładu przed radą.',
            'audience' => 'Dla nauczycieli wczesnoszkolnych',
            'problem' => 'Brak czasu na materiały.',
            'takeaway' => 'Jedna checklista do lekcji.',
            'sell_later' => 'tak',
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
