<?php

namespace Tests\Feature\GrowthOS;

use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\AI\Contracts\GrowthAiProvider;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GrowthAiPilotTest extends TestCase
{
    use RefreshDatabase;

    private FakeGrowthAiProvider $provider;

    private int $outputBufferLevel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputBufferLevel = ob_get_level();
        $this->withoutVite();
        config()->set('growth_os.enabled', true);
        config()->set('growth_ai.enabled', true);
        config()->set('growth_ai.limits.per_minute', 100);
        config()->set('growth_ai.limits.daily_per_user', 100);
        config()->set('growth_ai.circuit.failure_threshold', 100);

        Http::preventStrayRequests();

        $this->provider = new FakeGrowthAiProvider;
        $this->app->instance(GrowthAiProvider::class, $this->provider);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_disabled_flag_uses_local_simulation_without_external_provider(): void
    {
        config()->set('growth_ai.enabled', false);
        config()->set('growth_ai.provider', 'unsupported-provider');
        $this->app->forgetInstance(GrowthAiProvider::class);
        $user = $this->superAdmin();
        $this->createProject($user);
        $before = DemoTikWebinarProject::project()['concept'];

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'shorter',
            ])
            ->assertRedirect();

        $project = DemoTikWebinarProject::project();
        $this->assertSame(0, $this->provider->calls);
        $this->assertSame($before, $project['concept']);
        $this->assertIsArray($project['concept_ai_proposal']);
        $this->assertStringContainsString('Symulowana', $project['concept_ai_proposal']['note']);
    }

    public function test_only_super_admin_can_access_growth_ai_route(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'shorter',
            ])
            ->assertForbidden();

        $this->assertSame(0, $this->provider->calls);
    }

    public function test_detected_personal_data_is_not_sent_to_provider(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $before = DemoTikWebinarProject::project()['concept'];

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'practical',
                'instruction' => 'Dodaj kontakt uczestnika: uczestnik@example.com.',
            ])
            ->assertSessionHas('error', 'Treść wygląda na zawierającą dane osobowe lub poufne. Usuń je przed wysłaniem do AI.')
            ->assertRedirect();

        $project = DemoTikWebinarProject::project();
        $this->assertSame(0, $this->provider->calls);
        $this->assertSame($before, $project['concept']);
        $this->assertNull($project['concept_ai_proposal']);
    }

    public function test_valid_response_creates_proposal_and_only_apply_changes_concept(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $before = DemoTikWebinarProject::project()['concept'];

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'practical',
                'instruction' => 'Dodaj konkretne przykłady.',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('growth_ai_completed', true)
            ->assertRedirect();

        $project = DemoTikWebinarProject::project();
        $this->assertSame(1, $this->provider->calls);
        $this->assertSame('Canva AI w pracy nauczyciela', $this->provider->lastInput['direction']['topic'] ?? null);
        $this->assertStringContainsString('granicą sensu', $this->provider->lastInstructions);
        $this->assertSame($before, $project['concept']);
        $this->assertSame('Tytuł po korekcie AI', $project['concept_ai_proposal']['concept']['title']);
        $this->assertSame('real_ai', $project['concept_ai_proposal']['source']);
        $this->assertSame('openai', $project['concept_ai_proposal']['provider']);

        $jsonResponse = $this->actingAs($user)
            ->postJson(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'practical',
                'instruction' => 'Dodaj konkretne przykłady.',
            ]);

        $jsonResponse
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['proposal_html', 'message']);
        $this->assertStringContainsString('Tytuł po korekcie AI', $jsonResponse->json('proposal_html'));
        $this->assertStringContainsString('Zastosuj', $jsonResponse->json('proposal_html'));

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai.apply', DemoTikWebinarProject::PROJECT_ID))
            ->assertRedirect();

        $project = DemoTikWebinarProject::project();
        $this->assertSame('Tytuł po korekcie AI', $project['concept']['title']);
        $this->assertSame('', $project['direction']['audience']);
        $this->assertNull($project['concept_ai_proposal']);
    }

    public function test_reject_leaves_current_concept_unchanged(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $before = DemoTikWebinarProject::project()['concept'];

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'expand',
            ]);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai.reject', DemoTikWebinarProject::PROJECT_ID))
            ->assertRedirect();

        $project = DemoTikWebinarProject::project();
        $this->assertSame($before, $project['concept']);
        $this->assertNull($project['concept_ai_proposal']);
    }

    public function test_invalid_provider_payload_does_not_damage_session(): void
    {
        $this->provider->payload = ['title' => 'Niepełna odpowiedź'];
        $user = $this->superAdmin();
        $this->createProject($user);
        $before = DemoTikWebinarProject::project()['concept'];

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'expand',
            ])
            ->assertSessionHas('error', 'Nie udało się przygotować poprawnej propozycji AI. Twoja obecna koncepcja nie została zmieniona.')
            ->assertRedirect();

        $project = DemoTikWebinarProject::project();
        $this->assertSame($before, $project['concept']);
        $this->assertNull($project['concept_ai_proposal']);
    }

    public function test_invalid_json_error_does_not_damage_session(): void
    {
        $this->provider->exception = GrowthAiException::invalidResponse('invalid_json');
        $user = $this->superAdmin();
        $this->createProject($user);
        $before = DemoTikWebinarProject::project()['concept'];

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'expand',
            ])
            ->assertSessionHas('error', 'Nie udało się przygotować poprawnej propozycji AI. Twoja obecna koncepcja nie została zmieniona.')
            ->assertRedirect();

        $project = DemoTikWebinarProject::project();
        $this->assertSame($before, $project['concept']);
        $this->assertNull($project['concept_ai_proposal']);
    }

    public function test_timeout_does_not_block_manual_concept_work(): void
    {
        $this->provider->exception = GrowthAiException::unavailable('connection_error');
        $user = $this->superAdmin();
        $this->createProject($user);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'shorter',
            ])
            ->assertSessionHas('error', 'AI jest chwilowo niedostępne. Możesz kontynuować ręcznie.');

        $this->actingAs($user)
            ->put(route('growth.projects.concept.update', DemoTikWebinarProject::PROJECT_ID), [
                'title' => 'Ręcznie poprawiony tytuł',
                'subtitle' => 'Podtytuł',
                'promise' => 'Obietnica',
                'points' => "Punkt 1\nPunkt 2",
                'plan' => 'Plan',
                'cta' => 'CTA',
                'lead_magnet' => 'Materiał',
                'next_product' => 'nie',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('Ręcznie poprawiony tytuł', DemoTikWebinarProject::project()['concept']['title']);
    }

    public function test_from_direction_drafts_empty_concept_without_changing_direction_topic(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => 'NotebookLM w pracy nauczyciela',
        ]);
        $this->actingAs($user)->put(route('growth.projects.direction.update', DemoTikWebinarProject::PROJECT_ID), [
            'why_now' => 'Nauczyciele sprawdzają NotebookLM przy dokumentach.',
            'audience' => 'Nauczyciele pracujący z dokumentami.',
            'problem' => 'Trudno oddzielić bezpieczne użycie od konta szkolnego Google.',
            'takeaway' => 'Uczestnik ułoży jeden własny przykład.',
            'sell_later' => 'być może',
        ]);

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Wygeneruj lub zmień')
            ->assertSee('Wygeneruj na podstawie pomysłu i kierunku')
            ->assertSee('nie podmienia tematu');

        $before = DemoTikWebinarProject::project();

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'from_direction',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, $this->provider->calls);
        $this->assertSame('NotebookLM w pracy nauczyciela', $this->provider->lastInput['direction']['topic'] ?? null);
        $this->assertSame('Nauczyciele pracujący z dokumentami.', $this->provider->lastInput['direction']['audience'] ?? null);
        $this->assertStringContainsString('na podstawie direction', $this->provider->lastInstructions);
        $this->assertArrayNotHasKey('host', $this->provider->lastInput);

        $project = DemoTikWebinarProject::project();
        $this->assertSame($before['concept'], $project['concept']);
        $this->assertSame($before['topic'], $project['topic']);
        $this->assertSame($before['direction'], $project['direction']);
        $this->assertSame('Tytuł po korekcie AI', $project['concept_ai_proposal']['concept']['title']);
        $this->assertSame('być może', $project['concept_ai_proposal']['concept']['next_product']);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai.apply', DemoTikWebinarProject::PROJECT_ID))
            ->assertRedirect();

        $applied = DemoTikWebinarProject::project();
        $this->assertSame('Tytuł po korekcie AI', $applied['concept']['title']);
        $this->assertSame('NotebookLM w pracy nauczyciela', $applied['topic']);
        $this->assertSame('Nauczyciele pracujący z dokumentami.', $applied['direction']['audience']);
        $this->assertSame('Nauczyciele sprawdzają NotebookLM przy dokumentach.', $applied['direction']['why_now']);
    }

    public function test_from_direction_simulation_uses_direction_not_sample_copy(): void
    {
        config()->set('growth_ai.enabled', false);
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => 'NotebookLM w pracy nauczyciela',
        ]);
        $this->actingAs($user)->put(route('growth.projects.direction.update', DemoTikWebinarProject::PROJECT_ID), [
            'why_now' => 'Temat jest aktualny.',
            'audience' => 'Nauczyciele dokumentów.',
            'problem' => 'Bezpieczeństwo dokumentów.',
            'takeaway' => 'Jeden własny przykład pracy.',
            'sell_later' => 'nie',
        ]);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'from_direction',
            ]);

        $proposal = DemoTikWebinarProject::project()['concept_ai_proposal']['concept'];
        $this->assertSame('NotebookLM w pracy nauczyciela', $proposal['title']);
        $this->assertSame('Jeden własny przykład pracy.', $proposal['promise']);
        $this->assertSame('nie', $proposal['next_product']);
        $this->assertStringNotContainsString('Canva', json_encode($proposal, JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $this->provider->calls);
    }

    public function test_revision_sends_saved_direction_and_apply_leaves_it_approved(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => 'NotebookLM w pracy nauczyciela',
        ]);
        $this->actingAs($user)->put(route('growth.projects.direction.update', DemoTikWebinarProject::PROJECT_ID), [
            'why_now' => 'Nauczyciele sprawdzają NotebookLM przy dokumentach.',
            'audience' => 'Nauczyciele pracujący z dokumentami.',
            'problem' => 'Trudno oddzielić bezpieczne użycie od konta szkolnego Google.',
            'takeaway' => 'Uczestnik ułoży jeden własny przykład.',
            'sell_later' => 'nie',
        ]);
        $this->actingAs($user)->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']));
        $this->actingAs($user)->put(route('growth.projects.concept.update', DemoTikWebinarProject::PROJECT_ID), [
            'title' => 'NotebookLM w pracy nauczyciela',
            'subtitle' => 'Bezpieczna praca z dokumentami',
            'promise' => 'Uczestnik ułoży jeden własny przykład.',
            'points' => "Konto szkolne\nWłasny przykład",
            'plan' => 'Wprowadzenie, pokaz i ćwiczenie.',
            'cta' => 'Zapisz jeden przykład.',
            'lead_magnet' => 'Lista kontrolna.',
            'next_product' => 'nie',
        ]);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
                'intent' => 'shorter',
                'instruction' => 'Skróć plan.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, $this->provider->calls);
        $this->assertSame('NotebookLM w pracy nauczyciela', $this->provider->lastInput['direction']['topic'] ?? null);
        $this->assertSame('Nauczyciele pracujący z dokumentami.', $this->provider->lastInput['direction']['audience'] ?? null);
        $this->assertSame('Trudno oddzielić bezpieczne użycie od konta szkolnego Google.', $this->provider->lastInput['direction']['problem'] ?? null);
        $this->assertSame('nie', $this->provider->lastInput['direction']['sell_later'] ?? null);
        $this->assertArrayNotHasKey('host', $this->provider->lastInput);
        $this->assertStringContainsString('granicą sensu', $this->provider->lastInstructions);
        $this->assertStringNotContainsString('ułóż pierwszą koncepcję', $this->provider->lastInstructions);

        $beforeDirection = DemoTikWebinarProject::project()['direction'];
        $this->assertArrayHasKey('direction', DemoTikWebinarProject::project()['completed_steps']);

        $this->actingAs($user)
            ->post(route('growth.projects.concept.ai.apply', DemoTikWebinarProject::PROJECT_ID))
            ->assertRedirect();

        $applied = DemoTikWebinarProject::project();
        $this->assertSame($beforeDirection, $applied['direction']);
        $this->assertArrayHasKey('direction', $applied['completed_steps']);
        $this->assertSame('Tytuł po korekcie AI', $applied['concept']['title']);
    }

    private function createProject(User $user): void
    {
        $this->actingAs($user)->post(route('growth.projects.store'), [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => 'Canva AI w pracy nauczyciela',
        ]);
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

final class FakeGrowthAiProvider implements GrowthAiProvider
{
    public int $calls = 0;

    public string $lastTaskType = '';

    public string $lastInstructions = '';

    /** @var array<string, mixed> */
    public array $lastInput = [];

    public ?GrowthAiException $exception = null;

    /** @var array<string, mixed> */
    public array $payload = [
        'title' => 'Tytuł po korekcie AI',
        'subtitle' => 'Praktyczny podtytuł',
        'promise' => 'Uczestnik pozna praktyczny proces pracy.',
        'audience' => 'Nauczyciele i dyrektorzy szkół',
        'main_points' => ['Przykład pierwszy', 'Przykład drugi'],
        'agenda' => 'Wprowadzenie, pokaz, ćwiczenie i pytania.',
        'cta' => 'Pobierz checklistę.',
        'additional_material' => 'Checklista PDF.',
        'changed_fields' => ['title', 'subtitle', 'promise', 'audience', 'main_points', 'agenda', 'cta', 'additional_material'],
        'change_summary' => 'Koncepcja została doprecyzowana i upraktyczniona.',
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
        $this->lastInstructions = $instructions;
        $this->lastInput = $input;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return new AiProviderResponse(
            payload: $this->payload,
            provider: $this->name(),
            model: $this->model(),
            requestId: 'test-request-id',
            inputTokens: 100,
            outputTokens: 200,
            latencyMs: 10,
        );
    }
}
