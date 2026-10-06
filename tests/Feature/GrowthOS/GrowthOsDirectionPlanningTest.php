<?php

namespace Tests\Feature\GrowthOS;

use App\Http\Controllers\GrowthOS\ProjectController;
use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthCampaign;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\AI\Contracts\GrowthAiProvider;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Tasks\ConceptRevisionTask;
use App\Services\GrowthOS\AI\Tasks\DirectionPlanningTask;
use App\Services\GrowthOS\GrowthSessionConceptStore;
use App\Support\GrowthOS\DemoTikWebinarProject;
use App\Support\GrowthOS\GrowthAiRequestOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GrowthOsDirectionPlanningTest extends TestCase
{
    use RefreshDatabase;

    private FakeDirectionPlanningProvider $provider;

    private int $outputBufferLevel = 0;

    private ?string $logPath = null;

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

        $this->provider = new FakeDirectionPlanningProvider;
        $this->app->instance(GrowthAiProvider::class, $this->provider);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        if ($this->logPath !== null && is_file($this->logPath)) {
            unlink($this->logPath);
        }

        parent::tearDown();
    }

    public function test_create_form_shows_planning_assistant_without_static_ideas(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('growth.projects.create'))
            ->assertOk()
            ->assertSee('Asystent planowania')
            ->assertSee('Przeanalizuj temat i zaproponuj kierunek')
            ->assertSee('Zaplanuj webinar')
            ->assertDontSee('Pomóż mi znaleźć temat')
            ->assertDontSee('Canva AI w pracy nauczyciela')
            ->assertDontSee('TIK, który oszczędza czas przed końcem semestru');
    }

    public function test_ideas_page_keeps_examples_without_ai_label(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('growth.ideas.index'))
            ->assertOk()
            ->assertSee('Przykład tematu')
            ->assertSee('Canva AI w pracy nauczyciela')
            ->assertSee('To przykłady, nie propozycje AI')
            ->assertSee('Zaplanuj webinar');
    }

    public function test_cta_is_plan_webinar_on_dashboard_and_projects(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('growth.dashboard'))
            ->assertOk()
            ->assertSee('Zaplanuj webinar')
            ->assertDontSee('Zaplanuj webinar TIK');

        $this->actingAs($user)
            ->get(route('growth.projects.index'))
            ->assertOk()
            ->assertSee('Zaplanuj webinar')
            ->assertDontSee('Zaplanuj webinar TIK');
    }

    public function test_generate_stores_session_proposal_without_creating_a_project(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->from(route('growth.projects.create'))
            ->post(route('growth.projects.direction-planning'), $this->planningPayload())
            ->assertRedirect(route('growth.projects.create'))
            ->assertSessionHas('success')
            ->assertSessionHasInput('topic', $this->topic());

        $this->assertSame(1, $this->provider->calls);
        $this->assertSame(DirectionPlanningTask::TYPE, $this->provider->lastTaskType);
        $this->assertTrue($this->provider->lastOptions['web_search'] ?? false);
        $this->assertArrayNotHasKey('require_web_search', $this->provider->lastOptions);
        $this->assertTrue($this->provider->lastOptions['use_research_model'] ?? false);
        $this->assertSame($this->topic(), $this->provider->lastInput['campaign']['topic'] ?? null);
        $this->assertArrayNotHasKey('host', $this->provider->lastInput);
        $this->assertArrayNotHasKey('voice', $this->provider->lastInput);
        $this->assertStringContainsString('web_search', $this->provider->lastInstructions);
        $this->assertStringContainsString('Instrukcje znalezione na stronach', $this->provider->lastInstructions);
        $this->assertStringNotContainsString('Profil komunikacji', $this->provider->lastInstructions);
        $this->assertSame(0, GrowthCampaign::query()->count());
        $this->assertNull(DemoTikWebinarProject::project());

        $proposal = DemoTikWebinarProject::directionPlanningProposal();
        $this->assertIsArray($proposal);
        $this->assertSame('real_ai', $proposal['source']);
        $this->assertSame('Nauczyciele przedmiotowi korzystający z dokumentów na lekcji', $proposal['direction']['audience']);
        $this->assertSame('https://blog.google/notebooklm/', $proposal['sources'][0]['url']);

        $this->actingAs($user)
            ->get(route('growth.projects.create'))
            ->assertOk()
            ->assertSee('Kierunek proponowany przez AI')
            ->assertSee('blog.google')
            ->assertSee('Użyj tego kierunku')
            ->assertSee('Popraw propozycję')
            ->assertSee('Wyszukiwanie w sieci')
            ->assertDontSee('Popraw propozycję — szukaj w Internecie');
    }

    public function test_empty_topic_does_not_call_provider(): void
    {
        $this->actingAs($this->superAdmin())
            ->from(route('growth.projects.create'))
            ->post(route('growth.projects.direction-planning'), $this->planningPayload(['topic' => '']))
            ->assertRedirect(route('growth.projects.create'))
            ->assertSessionHas('error', ProjectController::DIRECTION_PLAN_EMPTY_TOPIC_MESSAGE);

        $this->assertSame(0, $this->provider->calls);
        $this->assertNull(DemoTikWebinarProject::directionPlanningProposal());
    }

    public function test_iterate_requires_instruction_and_skips_web_search(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.direction-planning'), $this->planningPayload());

        $this->actingAs($user)
            ->from(route('growth.projects.create'))
            ->post(route('growth.projects.direction-planning'), $this->planningPayload([
                'planning_mode' => DirectionPlanningTask::MODE_ITERATE,
            ]))
            ->assertRedirect(route('growth.projects.create'))
            ->assertSessionHas('error', ProjectController::DIRECTION_PLAN_ITERATE_EMPTY_MESSAGE);

        $this->assertSame(1, $this->provider->calls);

        $this->provider->payload['change_summary'] = 'Zwężono odbiorców.';
        $this->actingAs($user)
            ->post(route('growth.projects.direction-planning'), $this->planningPayload([
                'planning_mode' => DirectionPlanningTask::MODE_ITERATE,
                'planning_instruction' => 'Bardziej skup się na nauczycielach niż dyrektorach.',
                'ai_web_search' => '0',
            ]))
            ->assertRedirect(route('growth.projects.create'));

        $this->assertSame(2, $this->provider->calls);
        $this->assertSame('gpt-6.1-sol', $this->provider->lastOptions['model'] ?? null);
        $this->assertTrue(($this->provider->lastOptions['use_research_model'] ?? false) === true);
        $this->assertFalse(($this->provider->lastOptions['web_search'] ?? false) === true);
        $this->assertSame('iterate', $this->provider->lastInput['mode']);
        $this->assertArrayHasKey('previous_proposal', $this->provider->lastInput);
        $this->assertStringContainsString('Nie wyszukuj w Internecie', $this->provider->lastInstructions);
        $this->assertSame(1, DemoTikWebinarProject::directionPlanningProposal()['iteration_count']);
    }

    public function test_refresh_maps_to_iterate_with_web_search(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.direction-planning'), $this->planningPayload());

        $this->actingAs($user)
            ->post(route('growth.projects.direction-planning'), $this->planningPayload([
                'planning_mode' => DirectionPlanningTask::MODE_REFRESH,
                'planning_instruction' => 'Odśwież research.',
            ]))
            ->assertRedirect(route('growth.projects.create'));

        $this->assertTrue($this->provider->lastOptions['web_search'] ?? false);
        $this->assertArrayNotHasKey('require_web_search', $this->provider->lastOptions);
        $this->assertSame('iterate', $this->provider->lastInput['mode']);
    }

    public function test_stale_iterate_does_not_call_provider(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.direction-planning'), $this->planningPayload());

        $this->actingAs($user)
            ->post(route('growth.projects.direction-planning'), $this->planningPayload([
                'topic' => 'Inny temat webinaru',
                'planning_mode' => DirectionPlanningTask::MODE_ITERATE,
                'planning_instruction' => 'Zmień odbiorców.',
            ]))
            ->assertSessionHas('error', ProjectController::DIRECTION_PLAN_STALE_MESSAGE);

        $this->assertSame(1, $this->provider->calls);
    }

    public function test_use_proposal_writes_draft_direction_without_approval(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.direction-planning'), $this->planningPayload());

        $this->actingAs($user)
            ->post(route('growth.projects.store'), [
                ...$this->projectPayload(),
                'use_direction_ai_proposal' => '1',
            ])
            ->assertRedirect(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertSessionHas('success', 'Utworzono projekt webinaru. Kierunek z AI jest szkicem — zatwierdź go świadomie w workspace.');

        $project = DemoTikWebinarProject::project();
        $this->assertSame('Nauczyciele przedmiotowi korzystający z dokumentów na lekcji', $project['direction']['audience']);
        $this->assertArrayNotHasKey('direction', $project['completed_steps'] ?? []);
        $this->assertNull(DemoTikWebinarProject::directionPlanningProposal());

        $artifact = GrowthArtifact::query()->where('key', GrowthSessionConceptStore::DIRECTION_KEY)->first();
        $this->assertNotNull($artifact);
        $this->assertSame(GrowthArtifact::STATUS_DRAFT, $artifact->status);
        $this->assertSame(0, GrowthDecision::query()->where('type', GrowthSessionConceptStore::DECISION_DIRECTION_APPROVAL)->count());

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Nauczyciele przedmiotowi korzystający z dokumentów na lekcji')
            ->assertSee('Kierunek to szkic')
            ->assertDontSee('suggestion');
    }

    public function test_stale_use_flag_creates_project_without_applying_proposal(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.direction-planning'), $this->planningPayload());

        $this->actingAs($user)
            ->post(route('growth.projects.store'), [
                ...$this->projectPayload(['topic' => 'Inny temat webinaru']),
                'use_direction_ai_proposal' => '1',
            ])
            ->assertRedirect(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertSessionHas('error', ProjectController::DIRECTION_PLAN_STALE_MESSAGE);

        $project = DemoTikWebinarProject::project();
        $this->assertSame('', $project['direction']['why_now']);
        $this->assertSame('Inny temat webinaru', $project['concept']['title']);
        $this->assertSame(0, GrowthArtifact::query()->where('key', GrowthSessionConceptStore::DIRECTION_KEY)->count());
        $this->assertNull(DemoTikWebinarProject::directionPlanningProposal());
    }

    public function test_new_project_has_empty_direction_concept_and_material_drafts(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());

        $project = DemoTikWebinarProject::project();
        $this->assertSame('', $project['direction']['why_now']);
        $this->assertSame('', $project['direction']['sell_later']);
        $this->assertSame($this->topic(), $project['concept']['title']);
        $this->assertSame('', $project['concept']['promise']);
        $this->assertSame([], $project['concept']['points']);
        $this->assertSame('', $project['materials'][0]['draft']);
        $this->assertSame('', DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, 'youtube-description')['draft']);
    }

    public function test_disabled_flag_uses_local_simulation_without_sources_or_http(): void
    {
        config()->set('growth_ai.enabled', false);
        $this->app->forgetInstance(GrowthAiProvider::class);

        $this->actingAs($this->superAdmin())
            ->post(route('growth.projects.direction-planning'), $this->planningPayload())
            ->assertRedirect(route('growth.projects.create'))
            ->assertSessionHas('success');

        $this->assertSame(0, $this->provider->calls);
        $proposal = DemoTikWebinarProject::directionPlanningProposal();
        $this->assertSame('simulation', $proposal['source']);
        $this->assertSame([], $proposal['sources']);
        $this->assertFalse($proposal['web_search_used']);

        $this->actingAs($this->superAdmin())
            ->get(route('growth.projects.create'))
            ->assertOk()
            ->assertSee('Symulacja lokalna nie sprawdza Internetu.');
    }

    public function test_only_super_admin_can_plan_direction(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('growth.projects.direction-planning'), $this->planningPayload())
            ->assertForbidden();

        $this->assertSame(0, $this->provider->calls);
    }

    public function test_detected_personal_data_is_not_sent_to_provider(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('growth.projects.direction-planning'), $this->planningPayload([
                'planning_instruction' => 'Napisz do uczestnik@example.com',
            ]))
            ->assertSessionHas('error', 'Treść wygląda na zawierającą dane osobowe lub poufne. Usuń je przed wysłaniem do AI.');

        $this->assertSame(0, $this->provider->calls);
        $this->assertNull(DemoTikWebinarProject::directionPlanningProposal());
    }

    public function test_missing_web_search_soft_fails_with_note(): void
    {
        $this->provider->forceSkipWebSearch = true;

        $this->actingAs($this->superAdmin())
            ->post(route('growth.projects.direction-planning'), $this->planningPayload([
                'ai_web_search' => '1',
            ]))
            ->assertSessionHas('success');

        $proposal = DemoTikWebinarProject::directionPlanningProposal();
        $this->assertIsArray($proposal);
        $this->assertTrue($proposal['web_search_requested'] ?? false);
        $this->assertFalse($proposal['web_search_used'] ?? true);
        $this->assertSame(GrowthAiRequestOptions::WEB_SEARCH_SKIPPED_MESSAGE, $proposal['web_search_note'] ?? null);
        $this->assertSame([], $proposal['sources'] ?? null);
        $this->assertSame(0, GrowthCampaign::query()->count());
    }

    public function test_provider_failure_does_not_create_or_modify_project(): void
    {
        $this->provider->exception = GrowthAiException::unavailable('provider_server_error');

        $this->actingAs($this->superAdmin())
            ->post(route('growth.projects.direction-planning'), $this->planningPayload())
            ->assertSessionHas('error', 'AI jest chwilowo niedostępne. Możesz kontynuować ręcznie.');

        $this->assertSame(0, GrowthCampaign::query()->count());
        $this->assertNull(DemoTikWebinarProject::directionPlanningProposal());
        $this->assertNull(DemoTikWebinarProject::project());
    }

    public function test_invalid_structure_does_not_store_proposal(): void
    {
        $this->provider->payload['sell_later'] = 'yes';

        $this->actingAs($this->superAdmin())
            ->post(route('growth.projects.direction-planning'), $this->planningPayload())
            ->assertSessionHas('error', 'Nie udało się przygotować poprawnej propozycji AI. Możesz przygotować kierunek ręcznie.');

        $this->assertNull(DemoTikWebinarProject::directionPlanningProposal());
    }

    public function test_daily_limit_is_shared_with_concept_revision(): void
    {
        config()->set('growth_ai.limits.daily_per_user', 1);
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.direction-planning'), $this->planningPayload());
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());

        $this->provider->conceptPayload = $this->conceptPayload();
        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'shorter',
            ])
            ->assertSessionHas('error', 'Dzienny limit AI został wykorzystany. Możesz kontynuować ręcznie.');

        $this->assertSame(1, $this->provider->calls);
        $this->assertSame(DirectionPlanningTask::TYPE, $this->provider->lastTaskType);
    }

    public function test_concept_revision_still_runs_without_web_search_options(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());
        $this->provider->conceptPayload = $this->conceptPayload();

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'practical',
            ])
            ->assertRedirect();

        $this->assertSame(ConceptRevisionTask::TYPE, $this->provider->lastTaskType);
        $this->assertSame('gpt-6.1-sol', $this->provider->lastOptions['model'] ?? null);
        $this->assertArrayNotHasKey('use_research_model', $this->provider->lastOptions);
        $this->assertNotSame(DirectionPlanningTask::TYPE, $this->provider->lastTaskType);
    }

    public function test_log_records_task_metadata_without_content_or_sources(): void
    {
        $this->logPath = storage_path('logs/growth-ai-direction-test-'.uniqid().'.log');
        config()->set('logging.channels.growth_ai_direction_test', [
            'driver' => 'single',
            'path' => $this->logPath,
            'level' => 'debug',
        ]);
        config()->set('growth_ai.log_channel', 'growth_ai_direction_test');

        $this->actingAs($this->superAdmin())
            ->post(route('growth.projects.direction-planning'), $this->planningPayload());

        $log = (string) file_get_contents($this->logPath);
        $this->assertStringContainsString('"task_type":"'.DirectionPlanningTask::TYPE.'"', $log);
        $this->assertStringContainsString('"prompt_version":"'.DirectionPlanningTask::PROMPT_VERSION.'"', $log);
        $this->assertStringContainsString('"schema_version":"'.DirectionPlanningTask::SCHEMA_VERSION.'"', $log);
        $this->assertStringContainsString('"web_search_used":true', $log);
        $this->assertStringContainsString('"source_count":1', $log);
        $this->assertStringNotContainsString($this->topic(), $log);
        $this->assertStringNotContainsString('blog.google', $log);
        $this->assertStringNotContainsString('Nauczyciele przedmiotowi', $log);
        $this->assertStringNotContainsString('test-key', $log);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function planningPayload(array $overrides = []): array
    {
        return [
            'type' => 'Webinar TIK',
            'goal' => 'education',
            'topic' => $this->topic(),
            'live_date' => now()->addDays(7)->toDateString(),
            'planning_mode' => DirectionPlanningTask::MODE_GENERATE,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function projectPayload(array $overrides = []): array
    {
        return [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => $this->topic(),
            ...$overrides,
        ];
    }

    private function topic(): string
    {
        return 'NotebookLM w pracy nauczyciela — od przygotowania lekcji do pracy z dokumentami';
    }

    /**
     * @return array<string, mixed>
     */
    private function conceptPayload(): array
    {
        return [
            'title' => 'Tytuł po korekcie AI',
            'subtitle' => 'Praktyczny podtytuł',
            'promise' => 'Uczestnik pozna praktyczny proces pracy.',
            'audience' => 'Nauczyciele i dyrektorzy szkół',
            'main_points' => ['Przykład pierwszy', 'Przykład drugi'],
            'agenda' => 'Wprowadzenie, pokaz, ćwiczenie i pytania.',
            'cta' => 'Pobierz checklistę.',
            'additional_material' => 'Checklista PDF.',
            'changed_fields' => ['title'],
            'change_summary' => 'Koncepcja została doprecyzowana.',
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

final class FakeDirectionPlanningProvider implements GrowthAiProvider
{
    public int $calls = 0;

    public array $lastOptions = [];

    public array $lastInput = [];

    public string $lastTaskType = '';

    public string $lastInstructions = '';

    public ?GrowthAiException $exception = null;

    public bool $forceSkipWebSearch = false;

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

    /** @var array<string, mixed> */
    public array $conceptPayload = [];

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
        $this->lastInstructions = $instructions;
        $this->lastInput = $input;
        $this->lastOptions = $options;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        $webSearch = ($options['web_search'] ?? false) === true;
        $webSearchUsed = $webSearch && ! $this->forceSkipWebSearch;

        return new AiProviderResponse(
            payload: $taskType === ConceptRevisionTask::TYPE ? $this->conceptPayload : $this->payload,
            provider: $this->name(),
            model: (string) ($options['model'] ?? $this->model()),
            requestId: 'test-request-id',
            inputTokens: 100,
            outputTokens: 200,
            latencyMs: 10,
            webSearchUsed: $webSearchUsed,
            researchSources: $webSearchUsed ? [[
                'title' => 'NotebookLM blog',
                'url' => 'https://blog.google/notebooklm/',
                'domain' => 'blog.google',
            ]] : [],
            reasoningEffort: (string) ($options['reasoning_effort'] ?? ''),
            selectionSource: (string) ($options['selection_source'] ?? ''),
            webSearchRequested: $webSearch,
            webSearchNote: ($webSearch && ! $webSearchUsed)
                ? GrowthAiRequestOptions::WEB_SEARCH_SKIPPED_MESSAGE
                : null,
        );
    }
}
