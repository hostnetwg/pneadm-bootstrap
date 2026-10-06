<?php

namespace Tests\Feature\GrowthOS;

use App\Models\GrowthAiSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\AI\Contracts\GrowthAiProvider;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Support\GrowthAiExecutionOptions;
use App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GrowthOsAiExecutionSettingsTest extends TestCase
{
    use RefreshDatabase;

    private FakeExecutionProvider $provider;

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
        $this->provider = new FakeExecutionProvider;
        $this->app->instance(GrowthAiProvider::class, $this->provider);
        GrowthAiSetting::forgetSettingsCache();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_migration_seeds_sol_defaults(): void
    {
        $defaults = GrowthAiSetting::resolvedDefaults();

        $this->assertSame(GrowthAiModelCatalog::DEFAULT_GENERAL_MODEL, $defaults['general_model']);
        $this->assertSame(GrowthAiModelCatalog::DEFAULT_GENERAL_EFFORT, $defaults['general_reasoning_effort']);
        $this->assertSame(GrowthAiModelCatalog::DEFAULT_RESEARCH_MODEL, $defaults['research_model']);
        $this->assertSame(GrowthAiModelCatalog::DEFAULT_RESEARCH_EFFORT, $defaults['research_reasoning_effort']);
    }

    public function test_settings_page_saves_global_defaults_without_changing_on_local_request(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->put(route('growth.ai-settings.update'), [
                'general_model' => 'gpt-6-luna',
                'general_reasoning_effort' => 'low',
                'research_model' => 'gpt-6.1-sol',
                'research_reasoning_effort' => 'high',
            ])
            ->assertRedirect(route('growth.ai-settings.edit'));

        GrowthAiSetting::forgetSettingsCache();
        $defaults = GrowthAiSetting::resolvedDefaults();
        $this->assertSame('gpt-6-luna', $defaults['general_model']);
        $this->assertSame('low', $defaults['general_reasoning_effort']);

        $this->readyProject($user);
        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description']), [
                'mode' => 'generate',
                'ai_model' => 'gpt-6-astra',
                'ai_reasoning_effort' => 'max',
            ])
            ->assertRedirect();

        $this->assertSame('gpt-6-astra', $this->provider->lastOptions['model'] ?? null);
        $this->assertSame('max', $this->provider->lastOptions['reasoning_effort'] ?? null);

        GrowthAiSetting::forgetSettingsCache();
        $after = GrowthAiSetting::resolvedDefaults();
        $this->assertSame('gpt-6-luna', $after['general_model']);
        $this->assertSame('low', $after['general_reasoning_effort']);
    }

    public function test_invalid_model_is_rejected(): void
    {
        $this->expectException(GrowthAiException::class);
        GrowthAiExecutionOptions::assertAllowed('not-a-model', 'medium');
    }

    public function test_invalid_effort_for_model_is_rejected(): void
    {
        $this->expectException(GrowthAiException::class);
        GrowthAiExecutionOptions::assertAllowed('gpt-6.1-sol', 'none');
    }

    public function test_request_without_override_uses_default(): void
    {
        GrowthAiSetting::updateDefaults([
            'general_model' => 'gpt-5-mini',
            'general_reasoning_effort' => 'low',
            'research_model' => 'gpt-6.1-sol',
            'research_reasoning_effort' => 'high',
        ]);

        $options = GrowthAiExecutionOptions::resolve(null, null, GrowthAiModelCatalog::CHANNEL_GENERAL);

        $this->assertSame('gpt-5-mini', $options->model);
        $this->assertSame('low', $options->reasoningEffort);
        $this->assertSame(GrowthAiExecutionOptions::SOURCE_DEFAULT, $options->selectionSource);
    }

    public function test_local_override_uses_requested_model(): void
    {
        $options = GrowthAiExecutionOptions::resolve(
            'gpt-6-astra',
            'high',
            GrowthAiModelCatalog::CHANNEL_GENERAL,
        );

        $this->assertSame('gpt-6-astra', $options->model);
        $this->assertSame('high', $options->reasoningEffort);
        $this->assertSame(GrowthAiExecutionOptions::SOURCE_LOCAL, $options->selectionSource);
        $this->assertGreaterThanOrEqual(8000, $options->maxOutputTokens);
    }

    public function test_iterate_inherits_proposal_model_and_allows_change(): void
    {
        $inherited = GrowthAiExecutionOptions::resolve(
            null,
            null,
            GrowthAiModelCatalog::CHANNEL_GENERAL,
            inheritFrom: ['model' => 'gpt-6-luna', 'reasoning_effort' => 'low'],
        );
        $this->assertSame('gpt-6-luna', $inherited->model);
        $this->assertSame(GrowthAiExecutionOptions::SOURCE_INHERITED, $inherited->selectionSource);

        $changed = GrowthAiExecutionOptions::resolve(
            'gpt-6-astra',
            'xhigh',
            GrowthAiModelCatalog::CHANNEL_GENERAL,
            inheritFrom: ['model' => 'gpt-6-luna', 'reasoning_effort' => 'low'],
        );
        $this->assertSame('gpt-6-astra', $changed->model);
        $this->assertSame('xhigh', $changed->reasoningEffort);
        $this->assertSame(GrowthAiExecutionOptions::SOURCE_LOCAL, $changed->selectionSource);
    }

    public function test_research_models_support_web_search(): void
    {
        $ok = GrowthAiExecutionOptions::resolve(
            'gpt-6.1-sol',
            'high',
            GrowthAiModelCatalog::CHANNEL_RESEARCH,
            requiresWebSearch: true,
        );
        $this->assertTrue(GrowthAiModelCatalog::supportsWebSearch($ok->model));
    }

    public function test_provider_receives_model_and_effort_not_in_prompt_input(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description']), [
                'mode' => 'generate',
                'ai_model' => 'gpt-6.1-sol',
                'ai_reasoning_effort' => 'medium',
            ])
            ->assertRedirect();

        $this->assertSame('gpt-6.1-sol', $this->provider->lastOptions['model'] ?? null);
        $this->assertSame('medium', $this->provider->lastOptions['reasoning_effort'] ?? null);
        $this->assertArrayNotHasKey('model', $this->provider->lastInput);
        $this->assertArrayNotHasKey('reasoning_effort', $this->provider->lastInput);
        $this->assertStringNotContainsString('gpt-6.1-sol', $this->provider->lastInstructions);
    }

    public function test_proposal_stores_model_and_effort_metadata(): void
    {
        $user = $this->readyProject();
        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description']), [
                'mode' => 'generate',
                'ai_model' => 'gpt-6-astra',
                'ai_reasoning_effort' => 'high',
            ])
            ->assertRedirect();

        $project = DemoTikWebinarProject::requireProject(DemoTikWebinarProject::PROJECT_ID);
        $proposal = DemoTikWebinarProject::materialAiProposal($project, 'youtube-description');
        $this->assertNotNull($proposal);
        $this->assertSame('gpt-6-astra', $proposal['model'] ?? null);
        $this->assertSame('high', $proposal['reasoning_effort'] ?? null);
        $this->assertSame('local', $proposal['selection_source'] ?? null);
    }

    public function test_restore_recommended_sets_sol(): void
    {
        $user = $this->superAdmin();
        GrowthAiSetting::updateDefaults([
            'general_model' => 'gpt-5-mini',
            'general_reasoning_effort' => 'low',
            'research_model' => 'gpt-5.5',
            'research_reasoning_effort' => 'medium',
        ]);

        $this->actingAs($user)
            ->post(route('growth.ai-settings.restore'))
            ->assertRedirect(route('growth.ai-settings.edit'));

        GrowthAiSetting::forgetSettingsCache();
        $defaults = GrowthAiSetting::resolvedDefaults();
        $this->assertSame('gpt-6.1-sol', $defaults['general_model']);
        $this->assertSame('medium', $defaults['general_reasoning_effort']);
        $this->assertSame('high', $defaults['research_reasoning_effort']);
    }

    public function test_cost_uses_selected_model_rates(): void
    {
        $rates = GrowthAiModelCatalog::ratesFor('gpt-6-astra');
        $this->assertSame(5.00, $rates['input']);
        $this->assertSame(25.00, $rates['output']);
    }

    public function test_settings_page_shows_catalog_including_gpt5_mini(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)
            ->get(route('growth.ai-settings.edit'))
            ->assertOk()
            ->assertSee('GPT-6.1 Sol')
            ->assertSee('GPT-5 mini')
            ->assertSee('Przywróć zalecane');
    }

    private function readyProject(?User $user = null): User
    {
        $user ??= $this->superAdmin();
        $this->createProject($user);
        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'direction']))
            ->assertRedirect();
        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, 'concept']))
            ->assertRedirect();

        return $user;
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

final class FakeExecutionProvider implements GrowthAiProvider
{
    /** @var array<string, mixed> */
    public array $lastOptions = [];

    /** @var array<string, mixed> */
    public array $lastInput = [];

    public string $lastInstructions = '';

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
        $this->lastOptions = $options;
        $this->lastInput = $input;
        $this->lastInstructions = $instructions;

        return new AiProviderResponse(
            payload: [
                'draft' => "Webinar „Canva AI w pracy nauczyciela” — 6 października 2026 r., godz. 20:00.\n\nPokażemy praktyczne zastosowania Canva AI.",
                'change_summary' => 'Szkic testowy.',
            ],
            provider: $this->name(),
            model: (string) ($options['model'] ?? $this->model()),
            requestId: 'test-request',
            inputTokens: 10,
            outputTokens: 20,
            latencyMs: 5,
            reasoningEffort: (string) ($options['reasoning_effort'] ?? ''),
            selectionSource: (string) ($options['selection_source'] ?? ''),
        );
    }
}
