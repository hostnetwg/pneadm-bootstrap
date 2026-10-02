<?php

namespace Tests\Feature\GrowthOS;

use App\Http\Controllers\GrowthOS\ProjectController;
use App\Models\GrowthOS\GrowthArtifactVersion;
use App\Models\GrowthOS\GrowthCampaign;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\AI\Contracts\GrowthAiProvider;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Support\PneVoice;
use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Services\GrowthOS\GrowthSessionConceptStore;
use App\Support\GrowthOS\DemoTikWebinarProject;
use App\Support\GrowthOS\GrowthPeople;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Operator ≠ Presenter ≠ Communication Voice (DEC-035) and generate / refine / iterate for youtube-description (DEC-036).
 */
class GrowthOsPeopleVoiceTest extends TestCase
{
    use RefreshDatabase;

    private const MATERIAL = 'youtube-description';

    private const VOICE_PROFILE = 'Pisze jak praktyk do praktyków. Krótkie akapity, bez urzędowego języka.';

    private FakeVoiceDraftProvider $provider;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();
        config()->set('growth_os.enabled', true);
        config()->set('growth_ai.enabled', true);
        config()->set('growth_ai.limits.per_minute', 100);
        config()->set('growth_ai.limits.daily_per_user', 100);
        config()->set('growth_ai.circuit.failure_threshold', 100);

        Http::preventStrayRequests();

        $this->provider = new FakeVoiceDraftProvider;
        $this->app->instance(GrowthAiProvider::class, $this->provider);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_known_instructor_becomes_presenter_with_id_and_name_snapshot(): void
    {
        $user = $this->superAdmin();
        $anna = $this->instructor('Anna', 'Nowak');

        $this->createProject($user, ['host_source' => 'instructor', 'host_instructor_id' => $anna->id])
            ->assertSessionHasNoErrors();

        $campaign = $this->campaign();
        $this->assertSame($anna->id, $campaign->primary_instructor_id);
        $this->assertSame('Anna Nowak', $campaign->host_name);
        $this->assertSame('Anna Nowak', DemoTikWebinarProject::project()['host']);
    }

    public function test_external_presenter_has_no_instructor_and_keeps_host_name(): void
    {
        $user = $this->superAdmin();

        $this->createProject($user, ['host_source' => 'manual', 'host' => 'Jan Gość'])->assertSessionHasNoErrors();

        $campaign = $this->campaign();
        $this->assertNull($campaign->primary_instructor_id);
        $this->assertSame('Jan Gość', $campaign->host_name);
    }

    public function test_presenter_validation_messages(): void
    {
        $user = $this->superAdmin();

        $this->createProject($user, ['host_source' => 'instructor'])
            ->assertSessionHasErrors(['host_instructor_id' => 'Wybierz prowadzącego z listy.']);
        $this->createProject($user, ['host_source' => 'manual', 'host' => ''])
            ->assertSessionHasErrors(['host' => 'Wpisz imię i nazwisko prowadzącego.']);
    }

    public function test_voice_defaults_to_pne_neutral(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user)->assertSessionHasNoErrors();

        $this->assertNull($this->campaign()->communication_voice_instructor_id);
        $this->assertSame(GrowthPeople::VOICE_NEUTRAL, GrowthPeople::voice(DemoTikWebinarProject::project())['status']);

        $this->actingAs($user)
            ->get(route('growth.projects.create'))
            ->assertSee('PNE — neutralnie')
            ->assertSee('Głos komunikacji');
    }

    public function test_voice_can_point_to_instructor_different_from_presenter(): void
    {
        $user = $this->superAdmin();
        $anna = $this->instructor('Anna', 'Nowak');
        $waldemar = $this->instructor('Waldemar', 'Grabowski', self::VOICE_PROFILE);

        $this->createProject($user, [
            'host_source' => 'instructor',
            'host_instructor_id' => $anna->id,
            'voice_instructor_id' => $waldemar->id,
        ])->assertSessionHasNoErrors();

        $campaign = $this->campaign();
        $this->assertSame($anna->id, $campaign->primary_instructor_id);
        $this->assertSame($waldemar->id, $campaign->communication_voice_instructor_id);
        $this->assertSame($waldemar->id, $campaign->communicationVoiceInstructor->id);
    }

    public function test_voice_does_not_depend_on_logged_in_user(): void
    {
        $user = $this->superAdmin(['name' => 'Operator Systemu']);
        $anna = $this->instructor('Anna', 'Nowak', self::VOICE_PROFILE);

        $this->createProject($user, ['host' => 'Jan Gość', 'voice_instructor_id' => $anna->id]);
        $this->readyProject($user);
        $this->requestDraft($user);

        $this->assertSame('Anna Nowak', $this->provider->input['voice']['personal']['name']);
        $this->assertSame('Jan Gość', $this->provider->input['presenter']['name']);
        $this->assertStringNotContainsString('Operator Systemu', json_encode($this->provider->input, JSON_UNESCAPED_UNICODE));
        $this->assertSame($anna->id, $this->campaign()->communication_voice_instructor_id);
    }

    public function test_presenter_and_voice_are_restored_after_session_loss(): void
    {
        $user = $this->superAdmin();
        $anna = $this->instructor('Anna', 'Nowak');
        $waldemar = $this->instructor('Waldemar', 'Grabowski', self::VOICE_PROFILE);
        $this->createProject($user, [
            'host_source' => 'instructor',
            'host_instructor_id' => $anna->id,
            'voice_instructor_id' => $waldemar->id,
        ]);

        session()->forget(DemoTikWebinarProject::SESSION_PROJECT);

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Anna Nowak')
            ->assertSee('Waldemar Grabowski');

        $project = DemoTikWebinarProject::project();
        $this->assertSame($anna->id, $project['host_instructor_id']);
        $this->assertSame($waldemar->id, $project['voice_instructor_id']);
        $this->assertSame('Anna Nowak', $project['host']);
    }

    public function test_people_update_switches_presenter_and_voice(): void
    {
        $user = $this->superAdmin();
        $anna = $this->instructor('Anna', 'Nowak');
        $this->createProject($user, ['host' => 'Jan Gość']);

        $this->actingAs($user)
            ->put(route('growth.projects.host.update', DemoTikWebinarProject::PROJECT_ID), [
                'host_source' => 'instructor',
                'host_instructor_id' => $anna->id,
                'voice_instructor_id' => $anna->id,
            ])
            ->assertSessionHas('success');

        $campaign = $this->campaign();
        $this->assertSame($anna->id, $campaign->primary_instructor_id);
        $this->assertSame('Anna Nowak', $campaign->host_name);
        $this->assertSame($anna->id, $campaign->communication_voice_instructor_id);
    }

    public function test_deleted_or_inactive_voice_instructor_falls_back_to_pne_voice(): void
    {
        $user = $this->superAdmin();
        $anna = $this->instructor('Anna', 'Nowak', self::VOICE_PROFILE);
        $this->createProject($user, ['voice_instructor_id' => $anna->id]);
        $this->readyProject($user);

        $anna->update(['is_active' => false]);
        $this->assertSame(GrowthPeople::VOICE_UNAVAILABLE, GrowthPeople::voice(DemoTikWebinarProject::project())['status']);
        $this->requestDraft($user)->assertSessionHas('success');
        $this->assertNull($this->provider->input['voice']['personal']);

        $anna->delete();
        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertOk()
            ->assertSee('Instruktor wybrany jako głos jest nieaktywny albo usunięty.');
        $this->requestDraft($user)->assertSessionHas('success');
        $this->assertNull($this->provider->input['voice']['personal']);
    }

    public function test_deleted_instructor_cannot_be_selected(): void
    {
        $user = $this->superAdmin();
        $anna = $this->instructor('Anna', 'Nowak');
        $anna->delete();

        $this->createProject($user, ['voice_instructor_id' => $anna->id])->assertSessionHasErrors('voice_instructor_id');
    }

    public function test_empty_voice_profile_does_not_block_ai_and_shows_notice(): void
    {
        $user = $this->superAdmin();
        $anna = $this->instructor('Anna', 'Nowak');
        $this->createProject($user, ['voice_instructor_id' => $anna->id]);
        $this->readyProject($user);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSee('Ten instruktor nie ma jeszcze indywidualnego profilu komunikacji.');

        $this->requestDraft($user)->assertSessionHas('success');
        $this->assertNull($this->provider->input['voice']['personal']);
        $this->assertSame(PneVoice::rules(), $this->provider->input['voice']['pne_rules']);
    }

    public function test_instructor_private_fields_never_reach_ai(): void
    {
        $user = $this->superAdmin();
        $anna = Instructor::query()->create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna.prywatny@example.test',
            'phone' => '600700800',
            'bio' => 'BIO-TAJNE',
            'bio_html' => '<p>BIOHTML-TAJNE</p>',
            'notes' => 'NOTATKA-TAJNA',
            'ai_voice_profile' => self::VOICE_PROFILE,
            'is_active' => true,
        ]);
        $this->createProject($user, [
            'host_source' => 'instructor',
            'host_instructor_id' => $anna->id,
            'voice_instructor_id' => $anna->id,
        ]);
        $this->readyProject($user);

        $this->requestDraft($user);

        $encoded = json_encode($this->provider->input, JSON_UNESCAPED_UNICODE);
        foreach (['anna.prywatny', '600700800', 'BIO-TAJNE', 'BIOHTML-TAJNE', 'NOTATKA-TAJNA'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
        $this->assertSame(['name' => 'Anna Nowak', 'profile' => self::VOICE_PROFILE], $this->provider->input['voice']['personal']);
    }

    public function test_only_super_admin_can_edit_voice_profile(): void
    {
        $anna = $this->instructor('Anna', 'Nowak');
        $payload = ['first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => 'anna@example.test', 'ai_voice_profile' => 'Nowy styl'];

        $admin = User::factory()->create(['is_active' => true]);
        $this->actingAs($admin)->put(route('courses.instructors.update', $anna->id), $payload);
        $this->assertNull($anna->fresh()->ai_voice_profile);
        $this->actingAs($admin)->get(route('courses.instructors.edit', $anna->id))->assertDontSee('Profil komunikacji dla AI');

        $super = $this->superAdmin();
        $this->actingAs($super)->get(route('courses.instructors.edit', $anna->id))->assertSee('Profil komunikacji dla AI');
        $this->actingAs($super)->put(route('courses.instructors.update', $anna->id), $payload);
        $this->assertSame('Nowy styl', $anna->fresh()->ai_voice_profile);
    }

    public function test_generate_does_not_send_current_draft(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, 'Mój stary opis');

        $this->requestDraft($user, ['mode' => 'generate']);

        $this->assertArrayNotHasKey('current_draft', $this->provider->input);
        $this->assertArrayNotHasKey('author_draft', $this->provider->input);
        $this->assertStringNotContainsString('Mój stary opis', json_encode($this->provider->input, JSON_UNESCAPED_UNICODE));
        $this->assertSame(MaterialDraftTask::MODE_GENERATE, $this->proposal()['mode']);
    }

    public function test_refine_sends_unsaved_textarea_text(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, 'Zapisany opis');

        $this->requestDraft($user, ['mode' => 'refine', 'author_draft' => '  Mój niezapisany szkic opisu.  ']);

        $this->assertSame(MaterialDraftTask::MODE_REFINE, $this->provider->input['mode']);
        $this->assertSame('Mój niezapisany szkic opisu.', $this->provider->input['author_draft']);
        $this->assertStringContainsString('Redaguj tekst autora. Nie zastępuj jego głosu swoim.', $this->provider->instructions);
        $this->assertSame('Zapisany opis', $this->material()['draft']);
        $this->assertSame('Mój niezapisany szkic opisu.', $this->proposal()['compare_draft']);
    }

    public function test_refine_with_empty_text_makes_no_ai_call(): void
    {
        $user = $this->readyProject();

        $this->requestDraft($user, ['mode' => 'refine', 'author_draft' => '   '])
            ->assertSessionHas('error', ProjectController::MATERIAL_AI_REFINE_EMPTY_MESSAGE);

        $this->assertSame(0, $this->provider->calls);
        $this->assertNull($this->proposal());
    }

    public function test_iterate_uses_previous_proposal_and_new_instruction_without_saving(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, 'Zapisany opis');
        $this->requestDraft($user);
        $first = $this->proposal()['draft'];

        $this->provider->payload['draft'] = 'Druga wersja z poprawionym CTA.';
        $this->requestDraft($user, ['mode' => 'iterate', 'instruction' => 'Popraw tylko CTA'])->assertSessionHas('success');

        $this->assertSame(MaterialDraftTask::MODE_ITERATE, $this->provider->input['mode']);
        $this->assertSame($first, $this->provider->input['previous_proposal']);
        $this->assertSame('Popraw tylko CTA', $this->provider->input['instruction']);
        $this->assertSame('Druga wersja z poprawionym CTA.', $this->proposal()['draft']);
        $this->assertSame(1, $this->proposal()['iteration_count']);
        $this->assertSame('Zapisany opis', $this->material()['draft']);
        $this->assertSame(0, GrowthDecision::query()->where('type', 'like', 'material_ai_%')->count());
        $this->assertSame(0, GrowthArtifactVersion::query()->where('source', GrowthArtifactVersion::SOURCE_AI_APPLY)->count());
    }

    public function test_iterate_requires_instruction_and_existing_proposal(): void
    {
        $user = $this->readyProject();

        $this->requestDraft($user, ['mode' => 'iterate', 'instruction' => 'Popraw CTA'])
            ->assertSessionHas('error', ProjectController::MATERIAL_AI_STALE_MESSAGE);
        $this->requestDraft($user);
        $this->requestDraft($user, ['mode' => 'iterate', 'instruction' => ''])
            ->assertSessionHas('error', ProjectController::MATERIAL_AI_ITERATE_EMPTY_MESSAGE);

        $this->assertSame(1, $this->provider->calls);
    }

    public function test_apply_saves_only_final_iterated_proposal(): void
    {
        $user = $this->readyProject();
        $this->requestDraft($user);
        $this->provider->payload['draft'] = 'Wersja finalna po poprawce.';
        $this->requestDraft($user, ['mode' => 'iterate', 'instruction' => 'Skróć']);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSessionHas('success');

        $this->assertSame('Wersja finalna po poprawce.', $this->material()['draft']);
        $this->assertSame(1, GrowthArtifactVersion::query()->where('source', GrowthArtifactVersion::SOURCE_AI_APPLY)->count());
        $meta = GrowthDecision::query()->where('type', GrowthSessionConceptStore::DECISION_MATERIAL_AI_APPLY)->sole()->meta;
        $this->assertSame(MaterialDraftTask::MODE_ITERATE, $meta['ai_mode']);
        $this->assertSame(1, $meta['iteration_count']);
        $this->assertNull($meta['communication_voice_instructor_id']);
        $this->assertStringNotContainsString('Wersja finalna', json_encode($meta, JSON_UNESCAPED_UNICODE));
    }

    public function test_reject_keeps_material_and_returns_unsaved_refine_text(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, 'Zapisany opis');
        $this->requestDraft($user, ['mode' => 'refine', 'author_draft' => 'Mój niezapisany szkic.']);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.reject', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSessionHas('material_restored_draft', 'Mój niezapisany szkic.');

        $this->assertSame('Zapisany opis', $this->material()['draft']);
        $this->assertNull($this->proposal());
        $this->actingAs($user)
            ->withSession(['material_restored_draft' => 'Mój niezapisany szkic.'])
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSee('Mój niezapisany szkic.');
    }

    public function test_voice_owner_change_invalidates_proposal(): void
    {
        $user = $this->readyProject();
        $anna = $this->instructor('Anna', 'Nowak', self::VOICE_PROFILE);
        $this->requestDraft($user);

        $this->actingAs($user)->put(route('growth.projects.host.update', DemoTikWebinarProject::PROJECT_ID), [
            'host' => 'Waldemar Grabowski',
            'voice_instructor_id' => $anna->id,
        ]);

        $this->assertStaleApply($user);
    }

    public function test_voice_profile_change_invalidates_proposal(): void
    {
        $user = $this->superAdmin();
        $anna = $this->instructor('Anna', 'Nowak', self::VOICE_PROFILE);
        $this->createProject($user, ['voice_instructor_id' => $anna->id]);
        $this->readyProject($user);
        $this->requestDraft($user);

        $anna->update(['ai_voice_profile' => 'Zupełnie inny styl.']);

        $this->assertStaleApply($user);
    }

    public function test_presenter_and_voice_are_separate_in_task_input(): void
    {
        $user = $this->superAdmin();
        $waldemar = $this->instructor('Waldemar', 'Grabowski', self::VOICE_PROFILE);
        $this->createProject($user, ['host' => 'Jan Gość', 'voice_instructor_id' => $waldemar->id]);
        $this->readyProject($user);

        $this->requestDraft($user);

        $input = $this->provider->input;
        $this->assertSame(['name' => 'Jan Gość'], $input['presenter']);
        $this->assertSame(['name' => 'Waldemar Grabowski', 'profile' => self::VOICE_PROFILE], $input['voice']['personal']);
        $this->assertSame(PneVoice::VERSION, $input['voice']['pne_version']);
        $this->assertArrayNotHasKey('host_name', $input['campaign']);
        $this->assertStringContainsString('Głos komunikacji to nie jest prowadzący.', $this->provider->instructions);
        $this->assertStringContainsString('„zaprosiłem”', $this->provider->instructions);
        $this->assertSame(MaterialDraftTask::PROMPT_VERSION, $this->proposal()['prompt_version']);
    }

    public function test_voice_profile_with_personal_data_is_not_sent(): void
    {
        $user = $this->superAdmin();
        $anna = $this->instructor('Anna', 'Nowak', 'Pisz krótko. Kontakt: anna@example.com');
        $this->createProject($user, ['voice_instructor_id' => $anna->id]);
        $this->readyProject($user);

        $this->requestDraft($user)->assertSessionHas('error');

        $this->assertSame(0, $this->provider->calls);
    }

    public function test_other_materials_get_no_voice_or_mode(): void
    {
        $user = $this->superAdmin();
        $anna = $this->instructor('Anna', 'Nowak', self::VOICE_PROFILE);
        $this->createProject($user, ['voice_instructor_id' => $anna->id]);
        $this->readyProject($user);
        $this->provider->payload['draft'] = 'Post o webinarze. [LINK DO ZAPISU]';

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, 'facebook-post']), ['mode' => 'refine'])
            ->assertSessionHas('success');

        $input = $this->provider->input;
        foreach (['voice', 'presenter', 'mode', 'author_draft', 'previous_proposal'] as $key) {
            $this->assertArrayNotHasKey($key, $input);
        }
        $this->assertArrayHasKey('host_name', $input['campaign']);
        $this->assertArrayHasKey('current_draft', $input);
        $this->assertArrayNotHasKey('mode', DemoTikWebinarProject::materialAiProposal(DemoTikWebinarProject::project(), 'facebook-post'));
    }

    public function test_simulation_supports_all_modes(): void
    {
        config()->set('growth_ai.enabled', false);
        $user = $this->readyProject();

        $this->requestDraft($user, ['mode' => 'refine', 'author_draft' => "Mój   szkic.\n\n\nDrugi akapit."]);
        $this->assertSame("Mój szkic.\n\nDrugi akapit.", $this->proposal()['draft']);

        $this->requestDraft($user, ['mode' => 'iterate', 'instruction' => 'Skróć']);
        $this->assertSame(1, $this->proposal()['iteration_count']);
        $this->assertSame(0, $this->provider->calls);
    }

    private function assertStaleApply(User $user): void
    {
        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSessionHas('error', ProjectController::MATERIAL_AI_STALE_MESSAGE);

        $this->assertNull($this->proposal());
        $this->assertSame(0, GrowthDecision::query()->where('type', GrowthSessionConceptStore::DECISION_MATERIAL_AI_APPLY)->count());
    }

    private function instructor(string $first, string $last, ?string $profile = null): Instructor
    {
        return Instructor::query()->create([
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower($first.'.'.$last).'@example.test',
            'is_active' => true,
            'ai_voice_profile' => $profile,
        ]);
    }

    /**
     * @param  array<string, mixed>  $people
     */
    private function createProject(User $user, array $people = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post(route('growth.projects.store'), [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'goal' => 'education',
            'topic' => 'Canva AI w pracy nauczyciela',
            'host' => 'Waldemar Grabowski',
            ...$people,
        ]);
    }

    private function readyProject(?User $user = null): User
    {
        if ($user === null) {
            $user = $this->superAdmin();
            $this->createProject($user);
        }

        foreach (['direction', 'concept'] as $step) {
            $this->actingAs($user)
                ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, $step]))
                ->assertRedirect();
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requestDraft(User $user, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]), $data)
            ->assertRedirect(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]));
    }

    private function saveMaterial(User $user, string $draft): void
    {
        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]), [
                'status' => 'DRAFT',
                'draft' => $draft,
            ])
            ->assertRedirect();
    }

    /**
     * @return array<string, mixed>
     */
    private function material(): array
    {
        return DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, self::MATERIAL);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function proposal(): ?array
    {
        $project = DemoTikWebinarProject::project();

        return is_array($project) ? DemoTikWebinarProject::materialAiProposal($project, self::MATERIAL) : null;
    }

    private function campaign(): GrowthCampaign
    {
        return GrowthCampaign::query()->sole();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function superAdmin(array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'super_admin'],
            ['display_name' => 'super_admin', 'description' => 'Rola testowa Growth OS', 'is_system' => true, 'level' => 100],
        );

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true, ...$attributes]);
    }
}

final class FakeVoiceDraftProvider implements GrowthAiProvider
{
    public int $calls = 0;

    public string $instructions = '';

    /** @var array<string, mixed> */
    public array $input = [];

    /** @var array<string, mixed> */
    public array $payload = [
        'draft' => 'Webinar „Canva AI w pracy nauczyciela”. Zapraszamy nauczycieli.',
        'change_summary' => 'Przygotowano szkic opisu.',
    ];

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return 'test-model';
    }

    public function generateStructured(string $taskType, string $instructions, array $input, array $schema): AiProviderResponse
    {
        $this->calls++;
        $this->instructions = $instructions;
        $this->input = $input;

        return new AiProviderResponse(
            payload: $this->payload,
            provider: $this->name(),
            model: $this->model(),
            requestId: 'test-request-id',
            inputTokens: 10,
            outputTokens: 20,
            latencyMs: 5,
        );
    }
}
