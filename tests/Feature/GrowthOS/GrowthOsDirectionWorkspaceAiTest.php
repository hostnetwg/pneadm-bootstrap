<?php

namespace Tests\Feature\GrowthOS;

use App\Http\Controllers\GrowthOS\ProjectController;
use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\AI\Contracts\GrowthAiProvider;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Tasks\DirectionPlanningTask;
use App\Services\GrowthOS\GrowthSessionConceptStore;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GrowthOsDirectionWorkspaceAiTest extends TestCase
{
    use RefreshDatabase;

    private FakeDirectionWorkspaceProvider $provider;

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
        config()->set('growth_ai.research.model', 'gpt-5.5');

        Http::preventStrayRequests();

        $this->provider = new FakeDirectionWorkspaceProvider;
        $this->app->instance(GrowthAiProvider::class, $this->provider);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_workspace_shows_direction_assistant_until_direction_is_approved(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Asystent kierunku')
            ->assertSee('Popraw propozycję')
            ->assertSee('Popraw propozycję — szukaj w Internecie')
            ->assertSee('data-growth-ai-spinner', false)
            ->assertSee('gpt-5.5')
            ->assertSee('Zapisz kierunek');

        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']));

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Najpierw cofnij zatwierdzenie')
            ->assertDontSee('Asystent kierunku')
            ->assertDontSee('Popraw propozycję');
    }

    public function test_approved_direction_blocks_ai_without_calling_provider(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']));

        $this->actingAs($user)
            ->from(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->post(route('growth.projects.direction.ai', DemoTikWebinarProject::PROJECT_ID), $this->revisePayload())
            ->assertRedirect(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID).'#direction')
            ->assertSessionHas('error', ProjectController::DIRECTION_WORKSPACE_APPROVED_MESSAGE);

        $this->assertSame(0, $this->provider->calls);
        $project = DemoTikWebinarProject::project();
        $this->assertArrayHasKey('direction', $project['completed_steps']);
        $this->assertNull($project['direction_ai_proposal'] ?? null);
    }

    public function test_iterate_requires_instruction(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);

        $this->actingAs($user)
            ->from(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->post(route('growth.projects.direction.ai', DemoTikWebinarProject::PROJECT_ID), $this->revisePayload([
                'planning_instruction' => '   ',
            ]))
            ->assertSessionHas('error', ProjectController::DIRECTION_PLAN_ITERATE_EMPTY_MESSAGE);

        $this->assertSame(0, $this->provider->calls);
    }

    public function test_iterate_uses_posted_fields_and_does_not_save_direction(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);

        $this->actingAs($user)
            ->post(route('growth.projects.direction.ai', DemoTikWebinarProject::PROJECT_ID), $this->revisePayload([
                'audience' => 'Dyrektorzy szkół podstawowych',
                'planning_instruction' => 'Bardziej skup się na nauczycielach niż dyrektorach.',
            ]))
            ->assertRedirect(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID).'#direction')
            ->assertSessionHas('success');

        $this->assertSame(1, $this->provider->calls);
        $this->assertSame(DirectionPlanningTask::TYPE, $this->provider->lastTaskType);
        $this->assertSame(['use_research_model' => true], $this->provider->lastOptions);
        $this->assertSame('iterate', $this->provider->lastInput['mode']);
        $this->assertSame('Dyrektorzy szkół podstawowych', $this->provider->lastInput['previous_proposal']['direction']['audience']);
        $this->assertArrayNotHasKey('host', $this->provider->lastInput);
        $this->assertArrayNotHasKey('voice', $this->provider->lastInput);

        $project = DemoTikWebinarProject::project();
        $this->assertSame('', $project['direction']['audience']);
        $this->assertSame($this->topic(), $project['topic']);
        $this->assertSame('Nauczyciele przedmiotowi korzystający z dokumentów na lekcji', $project['direction_ai_proposal']['direction']['audience']);
        $this->assertSame('real_ai', $project['direction_ai_proposal']['source']);
        $this->assertFalse($project['direction_ai_proposal']['web_search_used']);
        $this->assertSame([], $project['direction_ai_proposal']['sources']);

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Kierunek proponowany przez AI')
            ->assertSee('Zmień na')
            ->assertSee('data-direction-apply-field="why_now"', false)
            ->assertSee('data-direction-apply-field="audience"', false)
            ->assertSee('data-direction-apply-field="sell_later"', false)
            ->assertSee('Nauczyciele przedmiotowi korzystający z dokumentów na lekcji')
            ->assertSee('Ta poprawka nie sprawdzała Internetu.')
            ->assertDontSee('Symulacja lokalna')
            ->assertSee('Zastosuj')
            ->assertSee('Odrzuć')
            ->assertSee('NotebookLM na lekcji: od dokumentu do zadania');
    }

    public function test_apply_writes_draft_without_approval_or_topic_change(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $this->actingAs($user)->post(
            route('growth.projects.direction.ai', DemoTikWebinarProject::PROJECT_ID),
            $this->revisePayload(['planning_instruction' => 'Doprecyzuj odbiorców.']),
        );

        $this->actingAs($user)
            ->post(route('growth.projects.direction.ai.apply', DemoTikWebinarProject::PROJECT_ID))
            ->assertRedirect(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID).'#direction')
            ->assertSessionHas('success', 'Zastosowano propozycję AI. Kierunek jest szkicem — zatwierdź go świadomie.');

        $project = DemoTikWebinarProject::project();
        $this->assertSame('Nauczyciele przedmiotowi korzystający z dokumentów na lekcji', $project['direction']['audience']);
        $this->assertSame($this->topic(), $project['topic']);
        $this->assertSame($this->topic(), $project['concept']['title']);
        $this->assertNull($project['direction_ai_proposal']);
        $this->assertArrayNotHasKey('direction', $project['completed_steps'] ?? []);

        $artifact = GrowthArtifact::query()->where('key', GrowthSessionConceptStore::DIRECTION_KEY)->first();
        $this->assertNotNull($artifact);
        $this->assertSame(GrowthArtifact::STATUS_DRAFT, $artifact->status);
        $this->assertSame(0, GrowthDecision::query()->where('type', GrowthSessionConceptStore::DECISION_DIRECTION_APPROVAL)->count());
    }

    public function test_reject_leaves_saved_direction(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $this->actingAs($user)->put(route('growth.projects.direction.update', DemoTikWebinarProject::PROJECT_ID), $this->savedDirection());
        $this->actingAs($user)->post(
            route('growth.projects.direction.ai', DemoTikWebinarProject::PROJECT_ID),
            $this->revisePayload(['planning_instruction' => 'Skróć problem.']),
        );

        $this->actingAs($user)
            ->post(route('growth.projects.direction.ai.reject', DemoTikWebinarProject::PROJECT_ID))
            ->assertSessionHas('success', 'Odrzucono propozycję AI. Kierunek bez zmian.');

        $project = DemoTikWebinarProject::project();
        $this->assertSame('Zapisany problem kierunku.', $project['direction']['problem']);
        $this->assertNull($project['direction_ai_proposal']);
    }

    public function test_manual_save_clears_proposal(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $this->actingAs($user)->post(
            route('growth.projects.direction.ai', DemoTikWebinarProject::PROJECT_ID),
            $this->revisePayload(['planning_instruction' => 'Zmień odbiorców.']),
        );

        $this->actingAs($user)->put(
            route('growth.projects.direction.update', DemoTikWebinarProject::PROJECT_ID),
            $this->savedDirection(['problem' => 'Problem zapisany ręcznie.']),
        );

        $project = DemoTikWebinarProject::project();
        $this->assertSame('Problem zapisany ręcznie.', $project['direction']['problem']);
        $this->assertNull($project['direction_ai_proposal']);

        $this->actingAs($user)
            ->post(route('growth.projects.direction.ai.apply', DemoTikWebinarProject::PROJECT_ID))
            ->assertSessionHas('error', 'Nie ma propozycji AI do zastosowania.');

        $this->assertSame('Problem zapisany ręcznie.', DemoTikWebinarProject::project()['direction']['problem']);
    }

    public function test_apply_is_stale_when_saved_direction_changed(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $this->actingAs($user)->post(
            route('growth.projects.direction.ai', DemoTikWebinarProject::PROJECT_ID),
            $this->revisePayload(['planning_instruction' => 'Doprecyzuj.']),
        );

        $project = DemoTikWebinarProject::project();
        $project['direction']['why_now'] = 'Kierunek zmieniony poza propozycją.';
        DemoTikWebinarProject::saveProject($project);

        $this->actingAs($user)
            ->post(route('growth.projects.direction.ai.apply', DemoTikWebinarProject::PROJECT_ID))
            ->assertSessionHas('error', ProjectController::DIRECTION_WORKSPACE_STALE_MESSAGE);

        $project = DemoTikWebinarProject::project();
        $this->assertSame('Kierunek zmieniony poza propozycją.', $project['direction']['why_now']);
        $this->assertNull($project['direction_ai_proposal']);
        $this->assertSame(0, GrowthArtifact::query()->where('key', GrowthSessionConceptStore::DIRECTION_KEY)->count());
    }

    public function test_refresh_requires_web_search(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);

        $this->actingAs($user)
            ->post(route('growth.projects.direction.ai', DemoTikWebinarProject::PROJECT_ID), $this->revisePayload([
                'planning_mode' => DirectionPlanningTask::MODE_REFRESH,
                'planning_instruction' => '',
            ]))
            ->assertRedirect();

        $this->assertSame(1, $this->provider->calls);
        $this->assertTrue($this->provider->lastOptions['web_search'] ?? false);
        $this->assertTrue($this->provider->lastOptions['require_web_search'] ?? false);
        $this->assertTrue($this->provider->lastOptions['use_research_model'] ?? false);
        $this->assertSame('refresh', $this->provider->lastInput['mode']);
    }

    public function test_simulation_has_no_sources_and_does_not_change_direction(): void
    {
        config()->set('growth_ai.enabled', false);
        $user = $this->superAdmin();
        $this->createProject($user);

        $this->actingAs($user)
            ->post(route('growth.projects.direction.ai', DemoTikWebinarProject::PROJECT_ID), $this->revisePayload([
                'why_now' => 'Kierunek wpisany w polu.',
                'problem' => 'Problem wpisany w polu.',
                'planning_instruction' => 'Dodaj akcent na dokumenty.',
            ]))
            ->assertSessionHas('success');

        $this->assertSame(0, $this->provider->calls);
        $project = DemoTikWebinarProject::project();
        $this->assertSame('', $project['direction']['why_now']);
        $this->assertSame('simulation', $project['direction_ai_proposal']['source']);
        $this->assertSame([], $project['direction_ai_proposal']['sources']);
        $this->assertFalse($project['direction_ai_proposal']['web_search_used']);
        $this->assertStringContainsString('Dodaj akcent na dokumenty.', $project['direction_ai_proposal']['direction']['problem']);

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Symulacja lokalna — bez sprawdzania Internetu.')
            ->assertDontSee('blog.google');
    }

    public function test_approving_direction_discards_unused_proposal(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $this->actingAs($user)->post(
            route('growth.projects.direction.ai', DemoTikWebinarProject::PROJECT_ID),
            $this->revisePayload(['planning_instruction' => 'Doprecyzuj.']),
        );

        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']));

        $this->assertNull(DemoTikWebinarProject::project()['direction_ai_proposal']);
    }

    public function test_apply_does_not_unapprove_direction(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $this->actingAs($user)->put(route('growth.projects.direction.update', DemoTikWebinarProject::PROJECT_ID), $this->savedDirection());
        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']));

        $project = DemoTikWebinarProject::project();
        $project['direction_ai_proposal'] = [
            'direction' => [
                'why_now' => 'Nowa propozycja AI.',
                'audience' => 'Inni odbiorcy.',
                'problem' => 'Inny problem.',
                'takeaway' => 'Inny rezultat.',
                'sell_later' => 'tak',
            ],
            'saved_fingerprint' => DemoTikWebinarProject::directionFieldsFingerprint($project['direction']),
        ];
        DemoTikWebinarProject::saveProject($project);

        $this->actingAs($user)
            ->post(route('growth.projects.direction.ai.apply', DemoTikWebinarProject::PROJECT_ID))
            ->assertSessionHas('error', ProjectController::DIRECTION_WORKSPACE_APPROVED_MESSAGE);

        $project = DemoTikWebinarProject::project();
        $this->assertSame('Zapisany problem kierunku.', $project['direction']['problem']);
        $this->assertArrayHasKey('direction', $project['completed_steps']);
        $this->assertNotNull($project['direction_ai_proposal']);
    }

    private function createProject(User $user): void
    {
        $this->actingAs($user)->post(route('growth.projects.store'), [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => $this->topic(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function revisePayload(array $overrides = []): array
    {
        return [
            'why_now' => 'Powód wpisany w polu, jeszcze nie zapisany.',
            'audience' => 'Nauczyciele z pola formularza.',
            'problem' => 'Problem wpisany w polu.',
            'takeaway' => 'Rezultat wpisany w polu.',
            'sell_later' => 'być może',
            'planning_mode' => DirectionPlanningTask::MODE_ITERATE,
            'planning_instruction' => '',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function savedDirection(array $overrides = []): array
    {
        return [
            'why_now' => 'Zapisany powód.',
            'audience' => 'Zapisani odbiorcy.',
            'problem' => 'Zapisany problem kierunku.',
            'takeaway' => 'Zapisany rezultat.',
            'sell_later' => 'nie',
            ...$overrides,
        ];
    }

    private function topic(): string
    {
        return 'NotebookLM w pracy nauczyciela — od przygotowania lekcji do pracy z dokumentami';
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

final class FakeDirectionWorkspaceProvider implements GrowthAiProvider
{
    public int $calls = 0;

    /** @var array<string, mixed> */
    public array $lastOptions = [];

    /** @var array<string, mixed> */
    public array $lastInput = [];

    public string $lastTaskType = '';

    /** @var array<string, mixed> */
    public array $payload = [
        'working_topic' => 'NotebookLM w pracy nauczyciela — od przygotowania lekcji do pracy z dokumentami',
        'why_now' => 'NotebookLM jest aktualnym narzędziem do pracy z dokumentami na lekcji.',
        'audience' => 'Nauczyciele przedmiotowi korzystający z dokumentów na lekcji',
        'problem' => 'Przygotowanie lekcji z wieloma źródłami zajmuje zbyt dużo czasu.',
        'takeaway' => 'Uczestnik ułoży prosty proces od notatki do pracy z dokumentem.',
        'sell_later' => 'być może',
        'title_suggestions' => ['NotebookLM na lekcji: od dokumentu do zadania'],
        'change_summary' => 'Zaproponowano kierunek na podstawie researchu.',
    ];

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return 'test-model';
    }

    public function generateStructured(
        string $taskType,
        string $instructions,
        array $input,
        array $schema,
        array $options = [],
    ): AiProviderResponse {
        $this->calls++;
        $this->lastTaskType = $taskType;
        $this->lastInput = $input;
        $this->lastOptions = $options;

        $webSearch = ($options['web_search'] ?? false) === true;

        return new AiProviderResponse(
            payload: $this->payload,
            provider: $this->name(),
            model: $this->model(),
            requestId: 'test-request-id',
            inputTokens: 100,
            outputTokens: 200,
            latencyMs: 10,
            webSearchUsed: $webSearch,
            researchSources: $webSearch ? [[
                'title' => 'NotebookLM blog',
                'url' => 'https://blog.google/notebooklm/',
                'domain' => 'blog.google',
            ]] : [],
        );
    }
}
