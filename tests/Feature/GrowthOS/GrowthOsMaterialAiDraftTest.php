<?php

namespace Tests\Feature\GrowthOS;

use App\Http\Controllers\GrowthOS\ProjectController;
use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\AI\Contracts\GrowthAiProvider;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\GrowthAiService;
use App\Services\GrowthOS\AI\Tasks\ConceptRevisionTask;
use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Services\GrowthOS\GrowthSessionConceptStore;
use App\Support\GrowthOS\DemoTikWebinarProject;
use App\Support\GrowthOS\MailHtmlFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GrowthOsMaterialAiDraftTest extends TestCase
{
    use RefreshDatabase;

    private const MATERIAL = 'youtube-description';

    private const FACEBOOK = 'facebook-post';

    private const GRAPHIC = 'main-graphic';

    private const MAIL = 'main-mail';

    private const REMINDER = 'reminder-mail';

    private const HOST_SCRIPT = 'host-script';

    private const FACEBOOK_DRAFT = "🎓 Canva AI w pracy nauczyciela — 6 października 2026 r., godz. 20:00.\n\nZapisz się: [LINK DO ZAPISU]\n\n#nauczyciele #TIK";

    private const AI_DRAFT = "Webinar „Canva AI w pracy nauczyciela” — 6 października 2026 r., godz. 20:00.\n\nPokażemy praktyczne zastosowania Canva AI w przygotowaniu materiałów.";

    private const UNAVAILABLE_MESSAGE = 'AI jest chwilowo niedostępne. Możesz kontynuować ręcznie.';

    private const INVALID_MESSAGE = 'Nie udało się przygotować poprawnej propozycji AI. Obecny szkic nie został zmieniony.';

    private FakeMaterialDraftProvider $provider;

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

        Http::preventStrayRequests();

        $this->provider = new FakeMaterialDraftProvider;
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

    public function test_material_page_shows_ai_action_only_for_supported_materials(): void
    {
        $user = $this->readyProject();

        foreach ([self::MATERIAL => 'Poproś AI o nowy szkic', self::FACEBOOK => 'Popraw mój szkic'] as $key => $button) {
            $this->actingAs($user)
                ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, $key]))
                ->assertOk()
                ->assertSee($button)
                ->assertSee('AI: '.\App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::badgeForChannel(\App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::CHANNEL_GENERAL));
        }

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, 'landing']))
            ->assertOk()
            ->assertDontSee('Poproś AI o szkic');
    }

    public function test_other_material_key_cannot_use_the_ai_flow(): void
    {
        $user = $this->readyProject();

        foreach (['landing', 'participant-material', 'follow-up'] as $key) {
            $this->actingAs($user)
                ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, $key]))
                ->assertNotFound();
            $this->actingAs($user)
                ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, $key]))
                ->assertNotFound();
        }

        $this->assertSame(0, $this->provider->calls);
    }

    public function test_missing_direction_approval_blocks_the_request(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $this->approve($user, 'concept');

        $this->requestDraft($user)
            ->assertSessionHas('error', ProjectController::MATERIAL_AI_PRECONDITION_MESSAGE);

        $this->assertSame(0, $this->provider->calls);
        $this->assertNull($this->proposal());
    }

    public function test_missing_concept_approval_blocks_the_request(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $this->approve($user, 'direction');

        $this->requestDraft($user)
            ->assertSessionHas('error', ProjectController::MATERIAL_AI_PRECONDITION_MESSAGE);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSee('Najpierw zatwierdź kierunek i koncepcję webinaru.');

        $this->assertSame(0, $this->provider->calls);
        $this->assertNull($this->proposal());
    }

    public function test_disabled_flag_uses_local_simulation_without_provider(): void
    {
        config()->set('growth_ai.enabled', false);
        $user = $this->readyProject();
        $before = $this->material();

        $this->requestDraft($user)->assertSessionHas('success');

        $proposal = $this->proposal();
        $this->assertSame(0, $this->provider->calls);
        $this->assertSame('simulation', $proposal['source']);
        $this->assertStringContainsString('Symulowana', $proposal['note']);
        $this->assertSame($before, $this->material());

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSee('AI: symulacja lokalna');
    }

    public function test_enabled_flag_uses_provider_with_material_task(): void
    {
        $user = $this->readyProject();

        $this->requestDraft($user)->assertSessionHas('success');

        $this->assertSame(1, $this->provider->calls);
        $this->assertSame(MaterialDraftTask::TYPE, $this->provider->taskType);
        $this->assertSame(['draft', 'change_summary'], $this->provider->schema['required']);
    }

    public function test_payload_contains_only_allow_listed_data(): void
    {
        $user = $this->readyProject();

        $this->requestDraft($user);

        $input = $this->provider->input;
        $this->assertSame(['material', 'campaign', 'direction', 'concept', 'presenter', 'voice', 'mode', 'style', 'instruction'], array_keys($input));
        $this->assertSame('', $input['instruction']);
        $this->assertSame(['emojis', 'address_form', 'address_form_overridden'], array_keys($input['style']));
        $this->assertSame(['key', 'name', 'type'], array_keys($input['material']));
        $this->assertSame(['working_topic', 'goal', 'live_date', 'live_time', 'timezone', 'address_form'], array_keys($input['campaign']));
        $this->assertSame('ty', $input['campaign']['address_form']);
        $this->assertSame('ty', $input['style']['address_form']);
        $this->assertFalse($input['style']['address_form_overridden']);
        $this->assertSame(['name'], array_keys($input['presenter']));
        $this->assertSame(['pne_version', 'pne_rules', 'personal'], array_keys($input['voice']));
        $this->assertNull($input['voice']['personal']);
        $this->assertSame(MaterialDraftTask::MODE_GENERATE, $input['mode']);
        $this->assertSame(['why_now', 'audience', 'problem', 'takeaway', 'sell_later'], array_keys($input['direction']));
        $this->assertSame(
            ['title', 'subtitle', 'promise', 'points', 'plan', 'cta', 'additional_material'],
            array_keys($input['concept']),
        );
        $this->assertSame(self::MATERIAL, $input['material']['key']);
        $this->assertSame('Canva AI w pracy nauczyciela', $input['campaign']['working_topic']);
        $this->assertSame('', $input['concept']['additional_material']);
        $this->assertArrayNotHasKey('next_product', $input['concept']);
    }

    public function test_emojis_are_off_by_default_and_checkbox_is_unchecked(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSee('Dodaj emotikony do opisu')
            ->assertDontSee('id="material_ai_emojis" checked', false);

        $this->requestDraft($user);

        $this->assertFalse($this->provider->input['style']['emojis']);
    }

    public function test_checked_emojis_box_is_sent_as_true(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]), ['emojis' => '1'])
            ->assertRedirect();

        $this->assertTrue($this->provider->input['style']['emojis']);
    }

    public function test_unchecked_emojis_box_is_sent_as_false(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]), ['emojis' => '0'])
            ->assertRedirect();

        $this->assertFalse($this->provider->input['style']['emojis']);
    }

    public function test_simulation_follows_emojis_choice(): void
    {
        config()->set('growth_ai.enabled', false);
        $user = $this->readyProject();

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]), ['emojis' => '1']);
        $this->assertStringContainsString('📅', $this->proposal()['draft']);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]), ['emojis' => '0']);
        $this->assertStringNotContainsString('📅', $this->proposal()['draft']);
    }

    public function test_additional_instruction_is_sent_and_kept_with_proposal(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSee('Dodatkowa instrukcja dla AI (opcjonalnie)');

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]), [
                'instruction' => '  Zacznij od pytania do nauczycieli.  ',
            ])
            ->assertSessionHas('success');

        $this->assertSame('Zacznij od pytania do nauczycieli.', $this->provider->input['instruction']);
        $this->assertSame('Zacznij od pytania do nauczycieli.', $this->proposal()['instruction']);
        $this->assertStringContainsString('instruction', $this->provider->instructions);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSee('Zacznij od pytania do nauczycieli.');
    }

    public function test_too_long_instruction_is_rejected_without_provider_call(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]), [
                'instruction' => str_repeat('a', (int) config('growth_ai.limits.max_instruction_chars') + 1),
            ])
            ->assertSessionHasErrors('instruction');

        $this->assertSame(0, $this->provider->calls);
        $this->assertNull($this->proposal());
    }

    public function test_personal_data_in_instruction_is_not_sent(): void
    {
        $user = $this->readyProject();
        $before = $this->material();

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]), [
                'instruction' => 'Dodaj kontakt: uczestnik@example.com',
            ])
            ->assertSessionHas('error', 'Treść wygląda na zawierającą dane osobowe lub poufne. Usuń je przed wysłaniem do AI.');

        $this->assertSame(0, $this->provider->calls);
        $this->assertSame($before, $this->material());
        $this->assertNull($this->proposal());
    }

    public function test_host_name_is_sent_from_campaign(): void
    {
        $user = $this->readyProject();

        $this->requestDraft($user);

        $this->assertSame('Waldemar Grabowski', $this->provider->input['presenter']['name']);
        $this->assertStringContainsString('presenter.name', $this->provider->instructions);
    }

    public function test_placeholder_host_is_sent_as_empty(): void
    {
        $user = $this->readyProject();
        $stored = session(DemoTikWebinarProject::SESSION_PROJECT);
        $stored['host'] = '—';
        session([DemoTikWebinarProject::SESSION_PROJECT => $stored]);
        \App\Models\GrowthOS\GrowthCampaign::query()->update(['host_name' => null]);

        $this->requestDraft($user);

        $this->assertSame('', $this->provider->input['presenter']['name']);
    }

    public function test_host_change_blocks_old_apply(): void
    {
        $user = $this->readyProject();
        $this->requestDraft($user);

        $this->actingAs($user)
            ->put(route('growth.projects.host.update', DemoTikWebinarProject::PROJECT_ID), ['host' => 'Anna Nowak'])
            ->assertRedirect();

        $this->assertStaleApply($user);
    }

    public function test_other_materials_are_not_sent(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, 'facebook-post', 'DRAFT', 'Sekretny szkic posta Facebook');
        $this->saveMaterial($user, 'main-mail', 'DRAFT', 'Sekretny szkic mailingu');

        $this->requestDraft($user);

        $encoded = json_encode($this->provider->input, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Sekretny szkic', $encoded);
        $this->assertStringNotContainsString('facebook-post', $encoded);
        $this->assertStringNotContainsString('main-mail', $encoded);
    }

    public function test_live_date_and_time_are_sent_in_app_timezone(): void
    {
        $user = $this->readyProject();

        $this->requestDraft($user);

        $campaign = $this->provider->input['campaign'];
        $this->assertSame(now()->addDays(7)->toDateString(), $campaign['live_date']);
        $this->assertSame('20:00', $campaign['live_time']);
        $this->assertSame(config('app.timezone'), $campaign['timezone']);
    }

    public function test_valid_response_creates_session_proposal(): void
    {
        $user = $this->readyProject();

        $this->requestDraft($user)->assertSessionHasNoErrors();

        $proposal = $this->proposal();
        $this->assertSame(self::AI_DRAFT, $proposal['draft']);
        $this->assertSame('real_ai', $proposal['source']);
        $this->assertSame('openai', $proposal['provider']);
        $this->assertSame('test-model', $proposal['model']);
        $this->assertSame(MaterialDraftTask::PROMPT_VERSION, $proposal['prompt_version']);
        $this->assertSame(MaterialDraftTask::SCHEMA_VERSION, $proposal['schema_version']);
        $this->assertSame(['direction', 'concept', 'material', 'host', 'voice'], array_keys($proposal['fingerprint']));

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSee('Obecny szkic')
            ->assertSee('Propozycja AI')
            ->assertSee('Zastosuj')
            ->assertSee('Odrzuć');
    }

    public function test_proposal_does_not_change_current_draft_or_database(): void
    {
        $user = $this->readyProject();
        $before = $this->material();

        $this->requestDraft($user);

        $this->assertSame($before['draft'], $this->material()['draft']);
        $this->assertNull(GrowthArtifact::query()->where('key', self::MATERIAL)->first());
        $this->assertSame(0, GrowthDecision::query()->where('type', 'like', 'material_ai_%')->count());
    }

    public function test_proposal_does_not_change_status(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, self::MATERIAL, 'APPROVED', 'Zatwierdzony opis');

        $this->requestDraft($user);

        $this->assertSame('APPROVED', $this->material()['status']);
        $this->assertSame(GrowthArtifact::STATUS_APPROVED, $this->artifact()->status);
    }

    public function test_apply_writes_draft_to_artifact(): void
    {
        $user = $this->readyProject();
        $this->requestDraft($user);

        $this->applyDraft($user)->assertSessionHas('success');

        $artifact = $this->artifact();
        $this->assertSame(self::AI_DRAFT, $artifact->payload['draft']);
        $this->assertSame(GrowthSessionConceptStore::MATERIAL_TYPE, $artifact->type);
        $this->assertSame(GrowthSessionConceptStore::SCHEMA_VERSION, $artifact->schema_version);
        $this->assertEqualsCanonicalizing(['status', 'draft'], array_keys($artifact->payload));
        $this->assertNull($this->proposal());

        session()->forget(DemoTikWebinarProject::SESSION_PROJECT);
        $this->assertSame(self::AI_DRAFT, $this->material()['draft']);
    }

    public function test_apply_sets_status_to_draft(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, self::MATERIAL, 'APPROVED', 'Zatwierdzony opis');
        $this->requestDraft($user);

        $this->applyDraft($user);

        $this->assertSame('DRAFT', $this->material()['status']);
        $this->assertSame(GrowthArtifact::STATUS_DRAFT, $this->artifact()->status);
        $this->assertSame('DRAFT', $this->artifact()->payload['status']);
    }

    public function test_apply_records_decision_without_proposal_content(): void
    {
        $user = $this->readyProject();
        $this->requestDraft($user);

        $this->applyDraft($user);

        $decision = GrowthDecision::query()->where('type', GrowthSessionConceptStore::DECISION_MATERIAL_AI_APPLY)->first();
        $this->assertNotNull($decision);
        $this->assertSame(GrowthDecision::STATUS_APPROVED, $decision->status);
        $this->assertSame($this->artifact()->id, $decision->growth_artifact_id);
        $this->assertSame($user->id, $decision->decided_by_user_id);
        $meta = $decision->meta;
        ksort($meta);
        $this->assertSame([
            'ai_mode' => MaterialDraftTask::MODE_GENERATE,
            'communication_voice_instructor_id' => null,
            'iteration_count' => 0,
            'material_key' => self::MATERIAL,
            'prompt_version' => MaterialDraftTask::PROMPT_VERSION,
            'source' => 'real_ai',
        ], $meta);
        $this->assertStringNotContainsString('Pokażemy praktyczne', json_encode($decision->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_reject_does_not_change_draft(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, self::MATERIAL, 'REVIEW', 'Mój ręczny opis');
        $this->requestDraft($user);

        $this->rejectDraft($user)->assertSessionHas('success');

        $this->assertSame('Mój ręczny opis', $this->material()['draft']);
        $this->assertSame('Mój ręczny opis', $this->artifact()->payload['draft']);
        $this->assertNull($this->proposal());
    }

    public function test_reject_does_not_change_status(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, self::MATERIAL, 'APPROVED', 'Zatwierdzony opis');
        $this->requestDraft($user);

        $this->rejectDraft($user);

        $this->assertSame('APPROVED', $this->material()['status']);
        $this->assertSame(GrowthArtifact::STATUS_APPROVED, $this->artifact()->status);
    }

    public function test_reject_records_decision(): void
    {
        $user = $this->readyProject();
        $this->requestDraft($user);

        $this->rejectDraft($user);

        $decision = GrowthDecision::query()->where('type', GrowthSessionConceptStore::DECISION_MATERIAL_AI_REJECT)->first();
        $this->assertNotNull($decision);
        $this->assertSame(GrowthDecision::STATUS_REJECTED, $decision->status);
        $this->assertNull($decision->growth_artifact_id);
        $this->assertSame(self::MATERIAL, $decision->meta['material_key']);
        $this->assertNull(GrowthArtifact::query()->where('key', self::MATERIAL)->first());
    }

    public function test_stale_fingerprint_blocks_apply(): void
    {
        $user = $this->readyProject();
        $this->requestDraft($user);

        $stored = session(DemoTikWebinarProject::SESSION_PROJECT);
        $stored['material_ai_proposals'][self::MATERIAL]['fingerprint']['concept'] = hash('sha256', 'inna koncepcja');
        session([DemoTikWebinarProject::SESSION_PROJECT => $stored]);

        $this->assertStaleApply($user);
    }

    public function test_direction_change_blocks_old_apply(): void
    {
        $user = $this->readyProject();
        $this->requestDraft($user);

        $this->actingAs($user)->put(route('growth.projects.direction.update', DemoTikWebinarProject::PROJECT_ID), [
            'topic' => DemoTikWebinarProject::project()['topic'] ?? 'Canva AI w pracy nauczyciela',
            'why_now' => 'Nowy powód',
            'audience' => 'Dyrektorzy szkół',
            'problem' => 'Nowy problem',
            'takeaway' => 'Nowa wartość',
            'sell_later' => 'nie',
        ]);
        $this->approve($user, 'direction');

        $this->assertStaleApply($user);
    }

    public function test_concept_change_blocks_old_apply(): void
    {
        $user = $this->readyProject();
        $this->requestDraft($user);

        $this->actingAs($user)->put(route('growth.projects.concept.update', DemoTikWebinarProject::PROJECT_ID), [
            'title' => 'Zmieniony tytuł',
            'subtitle' => 'Podtytuł',
            'promise' => 'Obietnica',
            'points' => "Punkt 1\nPunkt 2",
            'plan' => 'Plan',
            'cta' => 'CTA',
            'lead_magnet' => 'Materiał',
            'next_product' => 'nie',
        ]);
        $this->approve($user, 'concept');

        $this->assertStaleApply($user);
    }

    public function test_material_change_blocks_old_apply(): void
    {
        $user = $this->readyProject();
        $this->requestDraft($user);

        $this->saveMaterial($user, self::MATERIAL, 'REVIEW', 'Ręczna zmiana po wygenerowaniu');

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSessionHas('error', ProjectController::MATERIAL_AI_STALE_MESSAGE);

        $this->assertSame('Ręczna zmiana po wygenerowaniu', $this->material()['draft']);
        $this->assertSame('REVIEW', $this->material()['status']);
        $this->assertNull($this->proposal());
        $this->assertSame(0, GrowthDecision::query()->where('type', 'like', 'material_ai_%')->count());
    }

    public function test_invalid_json_does_not_change_material(): void
    {
        $this->provider->exception = GrowthAiException::invalidResponse('invalid_json');

        $this->assertProviderErrorLeavesMaterial(self::INVALID_MESSAGE);
    }

    public function test_timeout_does_not_change_material(): void
    {
        $this->provider->exception = GrowthAiException::unavailable('connection_error');

        $this->assertProviderErrorLeavesMaterial(self::UNAVAILABLE_MESSAGE);
    }

    public function test_rate_limit_429_does_not_change_material(): void
    {
        $this->provider->exception = GrowthAiException::unavailable('provider_rate_limited');

        $this->assertProviderErrorLeavesMaterial(self::UNAVAILABLE_MESSAGE);
    }

    public function test_server_error_5xx_does_not_change_material(): void
    {
        $this->provider->exception = GrowthAiException::unavailable('provider_server_error');

        $this->assertProviderErrorLeavesMaterial(self::UNAVAILABLE_MESSAGE);
    }

    public function test_ai_cannot_set_review_status(): void
    {
        $this->assertAiCannotSetStatus('REVIEW');
    }

    public function test_ai_cannot_set_approved_status(): void
    {
        $this->assertAiCannotSetStatus('APPROVED');
    }

    public function test_ai_cannot_set_published_status(): void
    {
        $this->assertAiCannotSetStatus('PUBLISHED');
    }

    public function test_log_distinguishes_material_draft_from_concept_revision_without_content(): void
    {
        $this->logPath = storage_path('logs/growth-ai-material-test-'.uniqid().'.log');
        config()->set('logging.channels.growth_ai_material_test', [
            'driver' => 'single',
            'path' => $this->logPath,
            'level' => 'debug',
        ]);
        config()->set('growth_ai.log_channel', 'growth_ai_material_test');
        $user = $this->readyProject();

        $this->requestDraft($user);
        $this->provider->payload = $this->conceptPayload();
        $this->actingAs($user)->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
            'intent' => 'shorter',
        ]);

        $log = (string) file_get_contents($this->logPath);
        $this->assertStringContainsString('"task_type":"'.MaterialDraftTask::TYPE.'"', $log);
        $this->assertStringContainsString('"prompt_version":"'.MaterialDraftTask::PROMPT_VERSION.'"', $log);
        $this->assertStringContainsString('"schema_version":"'.MaterialDraftTask::SCHEMA_VERSION.'"', $log);
        $this->assertStringContainsString('"task_type":"'.ConceptRevisionTask::TYPE.'"', $log);
        $this->assertStringNotContainsString('Pokażemy', $log);
        $this->assertStringNotContainsString('Canva', $log);
        $this->assertStringNotContainsString('Waldemar', $log);
    }

    public function test_daily_limit_is_shared_with_concept_revision(): void
    {
        config()->set('growth_ai.limits.daily_per_user', 1);
        $user = $this->readyProject();
        $this->provider->payload = $this->conceptPayload();

        $this->actingAs($user)->post(route('growth.projects.concept.ai', DemoTikWebinarProject::PROJECT_ID), [
            'intent' => 'shorter',
        ])->assertSessionHas('success');
        $this->actingAs($user)->post(route('growth.projects.concept.ai.reject', DemoTikWebinarProject::PROJECT_ID));
        $this->approve($user, 'concept');

        $this->requestDraft($user)
            ->assertSessionHas('error', GrowthAiService::DAILY_LIMIT_MESSAGE);

        $this->assertSame(1, $this->provider->calls);
        $this->assertNull($this->proposal());
    }

    public function test_daily_limit_message_offers_a_reset_that_clears_the_counter(): void
    {
        config()->set('growth_ai.limits.daily_per_user', 1);
        $this->logPath = storage_path('logs/growth-ai-material-test-'.uniqid().'.log');
        config()->set('logging.channels.growth_ai_material_test', [
            'driver' => 'single',
            'path' => $this->logPath,
            'level' => 'debug',
        ]);
        config()->set('growth_ai.log_channel', 'growth_ai_material_test');
        $user = $this->readyProject();
        $materialUrl = route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]);

        $this->requestDraft($user)->assertSessionHas('success');
        $this->actingAs($user)->get($materialUrl)
            ->assertSee(GrowthAiService::DAILY_LIMIT_MESSAGE)
            ->assertSee('data-bs-target="#growth-ai-limit-reset"', false);
        $this->actingAs($user)->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertSee('data-bs-target="#growth-ai-limit-reset"', false)
            ->assertSee('data-daily-limit-message="'.GrowthAiService::DAILY_LIMIT_MESSAGE.'"', false);
        $this->actingAs($user)->get(route('growth.projects.create'))
            ->assertSee('data-bs-target="#growth-ai-limit-reset"', false);

        $this->actingAs($user)
            ->post(route('growth.ai.limit.reset'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Zresetowano dzienny limit AI. Możesz znów prosić o propozycje.');

        $this->actingAs($user)->get($materialUrl)->assertDontSee(GrowthAiService::DAILY_LIMIT_MESSAGE);
        $this->requestDraft($user)->assertSessionHas('success');
        $this->assertSame(2, $this->provider->calls);
        $this->assertStringContainsString('"used_before_reset":1', (string) file_get_contents($this->logPath));

        $other = User::factory()->create(['is_active' => true]);
        $this->actingAs($other)->post(route('growth.ai.limit.reset'))->assertForbidden();
    }

    public function test_url_not_present_in_input_is_rejected(): void
    {
        $this->provider->payload['draft'] = self::AI_DRAFT."\n\nZapisy: https://example.com/zapisy";

        $this->assertProviderErrorLeavesMaterial(self::INVALID_MESSAGE);
    }

    public function test_personal_data_in_output_is_rejected_but_dates_are_allowed(): void
    {
        $this->provider->payload['draft'] = self::AI_DRAFT."\nKontakt: 600 700 800";

        $this->assertProviderErrorLeavesMaterial(self::INVALID_MESSAGE);
    }

    public function test_manual_save_still_works_and_creates_no_decision(): void
    {
        $this->provider->exception = GrowthAiException::unavailable('provider_server_error');
        $user = $this->readyProject();
        $this->requestDraft($user);

        $this->saveMaterial($user, self::MATERIAL, 'REVIEW', 'Ręczny opis mimo awarii AI');

        $this->assertSame('Ręczny opis mimo awarii AI', $this->artifact()->payload['draft']);
        $this->assertSame(0, GrowthDecision::query()->where('type', 'like', 'material_ai_%')->count());
    }

    public function test_no_real_openai_requests_are_made(): void
    {
        $user = $this->readyProject();

        $this->requestDraft($user);
        $this->applyDraft($user);

        $this->assertSame(1, $this->provider->calls);
        $this->assertInstanceOf(FakeMaterialDraftProvider::class, app(GrowthAiProvider::class));
        Http::assertNothingSent();
    }

    public function test_facebook_page_shows_hashtags_checkbox_and_link_placeholder(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::FACEBOOK]))
            ->assertOk()
            ->assertSee('Poproś AI o nowy szkic')
            ->assertSee('Popraw mój szkic')
            ->assertSee('Dodaj emotikony do posta')
            ->assertSee('id="material_ai_hashtags" checked', false)
            ->assertSee(MaterialDraftTask::LINK_PLACEHOLDER)
            ->assertSee('Opis YouTube nie jest zatwierdzony, więc AI go nie dostanie.');

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertDontSee('material_ai_hashtags');
    }

    public function test_facebook_payload_contains_only_allow_listed_data(): void
    {
        $user = $this->readyProject();

        $this->requestFor($user, self::FACEBOOK)->assertSessionHas('success');

        $input = $this->provider->input;
        $this->assertSame(
            ['material', 'campaign', 'direction', 'concept', 'source_materials', 'current_draft', 'style', 'instruction', 'mode'],
            array_keys($input),
        );
        $this->assertSame(['youtube_description'], array_keys($input['source_materials']));
        $this->assertSame(['emojis', 'hashtags', 'address_form', 'address_form_overridden'], array_keys($input['style']));
        $this->assertSame(['key' => self::FACEBOOK, 'name' => 'Post Facebook', 'type' => 'facebook_post'], $input['material']);
        $this->assertTrue($input['style']['hashtags']);
        $this->assertSame(MaterialDraftTask::TYPE, $this->provider->taskType);
        $this->assertStringContainsString('posta na Facebooku', $this->provider->instructions);
        $this->assertStringContainsString(MaterialDraftTask::LINK_PLACEHOLDER, $this->provider->instructions);
        $this->assertStringContainsString('style.hashtags', $this->provider->instructions);
        $this->assertSame('generate', $input['mode']);
        $this->assertSame('Canva AI w pracy nauczyciela', $input['campaign']['working_topic']);
    }

    public function test_facebook_refine_sends_the_unsaved_post_and_iterate_uses_the_proposal(): void
    {
        $user = $this->readyProject();

        $this->requestFor($user, self::FACEBOOK, [
            'mode' => 'refine',
            'author_draft' => 'Mój niezapisany post o NotebookLM.',
            'instruction' => 'Zostaw pierwsze zdanie',
        ]);

        $this->assertSame('refine', $this->provider->input['mode']);
        $this->assertSame('Mój niezapisany post o NotebookLM.', $this->provider->input['author_draft']);
        $this->assertSame('', $this->provider->input['current_draft']);
        $this->assertSame('Canva AI w pracy nauczyciela', $this->provider->input['campaign']['working_topic']);
        $this->assertArrayNotHasKey('voice', $this->provider->input);

        $this->requestFor($user, self::FACEBOOK, [
            'mode' => 'iterate',
            'instruction' => 'Skróć tylko zakończenie',
        ]);

        $this->assertSame('iterate', $this->provider->input['mode']);
        $this->assertArrayHasKey('previous_proposal', $this->provider->input);
        $this->assertSame(2, $this->provider->calls);
    }

    public function test_facebook_gets_youtube_description_only_when_approved(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, self::MATERIAL, 'REVIEW', 'Opis YouTube do sprawdzenia');

        $this->requestFor($user, self::FACEBOOK);
        $this->assertSame('', $this->provider->input['source_materials']['youtube_description']);

        $this->saveMaterial($user, self::MATERIAL, 'APPROVED', 'Zatwierdzony opis YouTube');

        $this->requestFor($user, self::FACEBOOK);
        $this->assertSame('Zatwierdzony opis YouTube', $this->provider->input['source_materials']['youtube_description']);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::FACEBOOK]))
            ->assertSee('AI użyje zatwierdzonego opisu YouTube jako źródła.');
    }

    public function test_facebook_never_receives_other_materials(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, 'main-mail', 'APPROVED', 'Sekretny szkic mailingu');
        $this->saveMaterial($user, 'host-script', 'APPROVED', 'Sekretny scenariusz');

        $this->requestFor($user, self::FACEBOOK);

        $encoded = json_encode($this->provider->input, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Sekretny', $encoded);
        $this->assertStringNotContainsString('main-mail', $encoded);
    }

    public function test_youtube_payload_does_not_get_facebook_fields(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, self::FACEBOOK, 'APPROVED', 'Sekretny szkic posta Facebook');

        $this->requestDraft($user);

        $this->assertArrayNotHasKey('source_materials', $this->provider->input);
        $this->assertSame(['emojis', 'address_form', 'address_form_overridden'], array_keys($this->provider->input['style']));
    }

    public function test_unchecked_hashtags_box_is_sent_as_false(): void
    {
        $user = $this->readyProject();

        $this->requestFor($user, self::FACEBOOK, ['hashtags' => '0']);

        $this->assertFalse($this->provider->input['style']['hashtags']);
    }

    public function test_facebook_apply_writes_post_with_facebook_versions(): void
    {
        $user = $this->readyProject();
        $this->provider->payload['draft'] = self::FACEBOOK_DRAFT;

        $this->requestFor($user, self::FACEBOOK);
        $proposal = $this->proposal(self::FACEBOOK);
        $this->assertSame(MaterialDraftTask::FACEBOOK_PROMPT_VERSION, $proposal['prompt_version']);
        $this->assertSame(MaterialDraftTask::FACEBOOK_SCHEMA_VERSION, $proposal['schema_version']);
        $this->assertSame(['direction', 'concept', 'material', 'host', 'source_materials'], array_keys($proposal['fingerprint']));

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::FACEBOOK]))
            ->assertSessionHas('success');

        $artifact = GrowthArtifact::query()->where('key', self::FACEBOOK)->first();
        $this->assertNotNull($artifact);
        $this->assertSame(self::FACEBOOK_DRAFT, $artifact->payload['draft']);
        $this->assertSame('DRAFT', DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, self::FACEBOOK)['status']);

        $decision = GrowthDecision::query()->where('type', GrowthSessionConceptStore::DECISION_MATERIAL_AI_APPLY)->first();
        $this->assertSame(self::FACEBOOK, $decision->meta['material_key']);
        $this->assertSame(MaterialDraftTask::FACEBOOK_PROMPT_VERSION, $decision->meta['prompt_version']);
    }

    public function test_youtube_description_change_blocks_old_facebook_apply(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, self::MATERIAL, 'APPROVED', 'Zatwierdzony opis YouTube');
        $this->requestFor($user, self::FACEBOOK);

        $this->saveMaterial($user, self::MATERIAL, 'REVIEW', 'Zatwierdzony opis YouTube');

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::FACEBOOK]))
            ->assertSessionHas('error', ProjectController::MATERIAL_AI_STALE_MESSAGE);

        $this->assertNull($this->proposal(self::FACEBOOK));
        $this->assertNull(GrowthArtifact::query()->where('key', self::FACEBOOK)->first());
    }

    public function test_youtube_and_facebook_proposals_are_kept_separately(): void
    {
        $user = $this->readyProject();

        $this->requestDraft($user);
        $this->provider->payload['draft'] = self::FACEBOOK_DRAFT;
        $this->requestFor($user, self::FACEBOOK);

        $this->assertSame(self::AI_DRAFT, $this->proposal()['draft']);
        $this->assertSame(self::FACEBOOK_DRAFT, $this->proposal(self::FACEBOOK)['draft']);

        $this->rejectDraft($user);

        $this->assertNull($this->proposal());
        $this->assertSame(self::FACEBOOK_DRAFT, $this->proposal(self::FACEBOOK)['draft']);
    }

    public function test_facebook_simulation_uses_placeholder_and_follows_hashtags_choice(): void
    {
        config()->set('growth_ai.enabled', false);
        $user = $this->readyProject();

        $this->requestFor($user, self::FACEBOOK, ['emojis' => '1', 'hashtags' => '1']);
        $draft = $this->proposal(self::FACEBOOK)['draft'];
        $this->assertStringContainsString(MaterialDraftTask::LINK_PLACEHOLDER, $draft);
        $this->assertStringContainsString('#nauczyciele', $draft);
        $this->assertStringContainsString('📅', $draft);

        $this->requestFor($user, self::FACEBOOK, ['emojis' => '0', 'hashtags' => '0']);
        $draft = $this->proposal(self::FACEBOOK)['draft'];
        $this->assertStringNotContainsString('#', $draft);
        $this->assertStringNotContainsString('📅', $draft);
        $this->assertSame(0, $this->provider->calls);
    }

    public function test_too_long_facebook_post_is_rejected(): void
    {
        $user = $this->readyProject();
        $this->provider->payload['draft'] = str_repeat('a', MaterialDraftTask::FACEBOOK_MAX_DRAFT_CHARS + 1);

        $this->requestFor($user, self::FACEBOOK)->assertSessionHas('error', self::INVALID_MESSAGE);

        $this->assertNull($this->proposal(self::FACEBOOK));
    }

    public function test_url_in_facebook_post_is_rejected(): void
    {
        $user = $this->readyProject();
        $this->provider->payload['draft'] = "Zapisz się: https://pnedu.pl/zapisy\n\n#TIK";

        $this->requestFor($user, self::FACEBOOK)->assertSessionHas('error', self::INVALID_MESSAGE);

        $this->assertNull($this->proposal(self::FACEBOOK));
    }

    public function test_log_uses_facebook_prompt_version(): void
    {
        $this->logPath = storage_path('logs/growth-ai-facebook-test-'.uniqid().'.log');
        config()->set('logging.channels.growth_ai_facebook_test', [
            'driver' => 'single',
            'path' => $this->logPath,
            'level' => 'debug',
        ]);
        config()->set('growth_ai.log_channel', 'growth_ai_facebook_test');
        $user = $this->readyProject();

        $this->requestFor($user, self::FACEBOOK);

        $log = (string) file_get_contents($this->logPath);
        $this->assertStringContainsString('"prompt_version":"'.MaterialDraftTask::FACEBOOK_PROMPT_VERSION.'"', $log);
        $this->assertStringContainsString('"schema_version":"'.MaterialDraftTask::FACEBOOK_SCHEMA_VERSION.'"', $log);
        $this->assertStringNotContainsString('Canva', $log);
    }

    public function test_graphic_page_shows_element_checkboxes_and_app_date(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC]))
            ->assertOk()
            ->assertSee('Poproś AI o nowy brief')
            ->assertSee('Popraw mój brief')
            ->assertSee('Popraw opis obrazu')
            ->assertSee('Dodatkowe elementy briefu')
            ->assertSee('id="material_ai_element_subtitle" checked', false)
            ->assertSee('id="material_ai_element_alt_text" checked', false)
            ->assertSee($this->liveLabel())
            ->assertDontSee('material_ai_emojis');
    }

    public function test_graphic_payload_and_schema(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->graphicPayload();

        $this->requestFor($user, self::GRAPHIC)->assertSessionHas('success');

        $input = $this->provider->input;
        $this->assertSame(['material', 'campaign', 'direction', 'concept', 'source_materials', 'current_draft', 'style', 'instruction', 'mode'], array_keys($input));
        $this->assertSame('generate', $input['mode']);
        $this->assertSame(['youtube_description' => ''], $input['source_materials']);
        $this->assertSame(['working_topic', 'goal', 'live_date', 'live_time', 'timezone', 'host_name', 'address_form', 'live_label'], array_keys($input['campaign']));
        $this->assertSame($this->liveLabel(), $input['campaign']['live_label']);
        $this->assertSame(['formats', 'elements', 'address_form', 'address_form_overridden'], array_keys($input['style']));
        $this->assertSame(array_keys(MaterialDraftTask::GRAPHIC_OPTIONAL_ELEMENTS), array_keys($input['style']['elements']));
        $this->assertSame(['key' => self::GRAPHIC, 'name' => 'Grafika główna', 'type' => 'graphic_brief'], $input['material']);
        $this->assertSame(
            ['headline', 'subtitle', 'cta', 'visual_direction', 'image_prompt', 'alt_text', 'change_summary'],
            $this->provider->schema['required'],
        );
        $this->assertStringContainsString('bez żadnego tekstu', $this->provider->instructions);
        $this->assertStringContainsString('campaign.working_topic', $this->provider->instructions);
        $this->assertStringContainsString('source_materials.youtube_description', $this->provider->instructions);
    }

    public function test_graphic_brief_uses_only_approved_youtube_description(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->graphicPayload();

        $this->saveMaterial($user, self::MATERIAL, 'REVIEW', 'Opis YouTube do sprawdzenia');
        $this->requestFor($user, self::GRAPHIC);
        $this->assertSame('', $this->provider->input['source_materials']['youtube_description']);
        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC]))
            ->assertSee('Opis YouTube nie jest zatwierdzony, więc AI go nie dostanie.');

        $this->saveMaterial($user, self::MATERIAL, 'APPROVED', 'Zatwierdzony opis YouTube');
        $this->requestFor($user, self::GRAPHIC);
        $this->assertSame('Zatwierdzony opis YouTube', $this->provider->input['source_materials']['youtube_description']);
        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC]))
            ->assertSee('AI użyje zatwierdzonego opisu YouTube jako źródła.');
    }

    public function test_graphic_proposal_goes_stale_when_youtube_description_changes(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->graphicPayload();
        $this->saveMaterial($user, self::MATERIAL, 'APPROVED', 'Zatwierdzony opis YouTube');
        $this->requestFor($user, self::GRAPHIC);

        $this->saveMaterial($user, self::MATERIAL, 'APPROVED', 'Inny zatwierdzony opis YouTube');

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC]))
            ->assertSessionHas('error', ProjectController::MATERIAL_AI_STALE_MESSAGE);
        $this->assertNull($this->proposal(self::GRAPHIC));
    }

    public function test_graphic_brief_is_composed_with_app_date_and_host(): void
    {
        $user = $this->readyProject();
        $payload = $this->graphicPayload();
        $payload['subtitle'] = 'Praktyczny webinar TIK';
        $this->provider->payload = $payload;

        $this->requestFor($user, self::GRAPHIC);

        $draft = $this->proposal(self::GRAPHIC)['draft'];
        $this->assertStringContainsString('Formaty: '.MaterialDraftTask::GRAPHIC_FORMATS, $draft);
        $this->assertStringContainsString('Nagłówek: Canva AI w pracy nauczyciela', $draft);
        $this->assertStringContainsString('Podtytuł: Praktyczny webinar TIK', $draft);
        $this->assertStringContainsString('Termin: '.$this->liveLabel(), $draft);
        $this->assertStringContainsString('Prowadzący: Waldemar Grabowski', $draft);
        $this->assertStringContainsString('Wezwanie do działania: Zapisz się', $draft);
        $this->assertStringContainsString("Kierunek wizualny:\nGranat i biel.", $draft);
        $this->assertStringContainsString("Opis obrazu dla AI (bez tekstu na obrazie):\nBiurko z laptopem.", $draft);
        $this->assertStringContainsString("Tekst alternatywny (alt):\nGrafika webinaru.", $draft);
        $this->assertSame(MaterialDraftTask::GRAPHIC_PROMPT_VERSION, $this->proposal(self::GRAPHIC)['prompt_version']);
    }

    public function test_unchecked_graphic_elements_are_sent_false_and_left_out(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->graphicPayload();

        $this->requestFor($user, self::GRAPHIC, [
            'elements' => ['subtitle' => '0', 'host' => '0', 'cta' => '1', 'image_prompt' => '0', 'alt_text' => '0'],
        ]);

        $elements = $this->provider->input['style']['elements'];
        $this->assertSame(['subtitle' => false, 'host' => false, 'cta' => true, 'image_prompt' => false, 'alt_text' => false], $elements);

        $draft = $this->proposal(self::GRAPHIC)['draft'];
        $this->assertStringNotContainsString('Podtytuł:', $draft);
        $this->assertStringNotContainsString('Prowadzący:', $draft);
        $this->assertStringNotContainsString('Opis obrazu dla AI', $draft);
        $this->assertStringNotContainsString('Tekst alternatywny', $draft);
        $this->assertStringContainsString('Wezwanie do działania: Zapisz się', $draft);
        $this->assertStringContainsString('Termin: ', $draft);
    }

    public function test_graphic_response_with_missing_extra_or_empty_fields_is_rejected(): void
    {
        $user = $this->readyProject();

        $missing = $this->graphicPayload();
        unset($missing['alt_text']);
        $extra = $this->graphicPayload() + ['live_date' => '2030-01-01'];
        $empty = array_merge($this->graphicPayload(), ['headline' => '  ']);

        foreach ([$missing, $extra, $empty] as $payload) {
            $this->provider->payload = $payload;
            $this->requestFor($user, self::GRAPHIC)->assertSessionHas('error', self::INVALID_MESSAGE);
            $this->assertNull($this->proposal(self::GRAPHIC));
        }
    }

    public function test_graphic_apply_writes_brief_with_graphic_versions(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->graphicPayload();
        $this->requestFor($user, self::GRAPHIC);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC]))
            ->assertSessionHas('success');

        $artifact = GrowthArtifact::query()->where('key', self::GRAPHIC)->first();
        $this->assertNotNull($artifact);
        $this->assertStringContainsString('Termin: '.$this->liveLabel(), $artifact->payload['draft']);
        $decision = GrowthDecision::query()->where('type', GrowthSessionConceptStore::DECISION_MATERIAL_AI_APPLY)->first();
        $this->assertSame(self::GRAPHIC, $decision->meta['material_key']);
        $this->assertSame(MaterialDraftTask::GRAPHIC_PROMPT_VERSION, $decision->meta['prompt_version']);
    }

    public function test_graphic_simulation_uses_app_date_and_follows_elements(): void
    {
        config()->set('growth_ai.enabled', false);
        $user = $this->readyProject();

        $this->requestFor($user, self::GRAPHIC, ['elements' => ['alt_text' => '0']]);

        $draft = $this->proposal(self::GRAPHIC)['draft'];
        $this->assertStringContainsString('Termin: '.$this->liveLabel(), $draft);
        $this->assertStringContainsString('Nagłówek: Canva AI w pracy nauczyciela', $draft);
        $this->assertStringNotContainsString('Tekst alternatywny', $draft);
        $this->assertSame(0, $this->provider->calls);
    }

    public function test_graphic_refine_sends_the_unsaved_brief_and_iterate_uses_the_proposal(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->graphicPayload();

        $this->requestFor($user, self::GRAPHIC, [
            'mode' => 'refine',
            'author_draft' => "Nagłówek: Mój brief\n\nKierunek wizualny:\nGranat.",
            'instruction' => 'Zostaw nagłówek',
        ]);

        $this->assertSame('refine', $this->provider->input['mode']);
        $this->assertSame("Nagłówek: Mój brief\n\nKierunek wizualny:\nGranat.", $this->provider->input['author_draft']);
        $this->assertSame('', $this->provider->input['current_draft']);
        $this->assertSame('Canva AI w pracy nauczyciela', $this->provider->input['campaign']['working_topic']);

        $this->requestFor($user, self::GRAPHIC, [
            'mode' => 'iterate',
            'instruction' => 'Skróć tylko kierunek wizualny',
        ]);

        $this->assertSame('iterate', $this->provider->input['mode']);
        $this->assertArrayHasKey('previous_proposal', $this->provider->input);
        $this->assertSame(2, $this->provider->calls);
    }

    public function test_image_description_refine_apply_changes_only_the_description(): void
    {
        $user = $this->readyProject();
        $brief = "Nagłówek: Canva AI w szkole\nTermin: wtorek\n\nKierunek wizualny:\nGranat i biel.\n\nOpis obrazu dla AI (bez tekstu na obrazie):\nStary opis biurka.";
        $this->saveMaterial($user, self::GRAPHIC, 'REVIEW', $brief);
        $this->provider->payload = [
            'description' => 'Nauczyciel siedzi przodem do uczniów, za nim tablica.',
            'change_summary' => 'Poprawiono układ klasy.',
        ];

        $this->actingAs($user)
            ->post(route('growth.projects.materials.images.description.ai', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC]), [
                'mode' => 'refine',
                'author_draft' => 'Stary opis biurka, uczniowie za nauczycielem.',
                'description_instruction' => 'Nauczyciel przodem do uczniów',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('graphic_image_description', $this->provider->taskType);
        $this->assertSame('refine', $this->provider->input['mode']);
        $this->assertSame('Stary opis biurka, uczniowie za nauczycielem.', $this->provider->input['author_draft']);
        $this->assertSame('Canva AI w pracy nauczyciela', $this->provider->input['campaign']['working_topic']);
        $this->assertSame('Granat i biel.', $this->provider->input['visual_direction']);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.images.description.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC]))
            ->assertSessionHas('success');

        $graphic = DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC);
        $draft = (string) $graphic['draft'];
        $this->assertStringContainsString('Nagłówek: Canva AI w szkole', $draft);
        $this->assertStringContainsString("Opis obrazu dla AI (bez tekstu na obrazie):\nNauczyciel siedzi przodem do uczniów, za nim tablica.", $draft);
        $this->assertStringNotContainsString('Stary opis biurka.', $draft);
        $this->assertSame('DRAFT', $graphic['status']);
    }

    public function test_mail_page_offers_length_switch_and_emojis(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertOk()
            ->assertSee('Poproś AI o nowy szkic')
            ->assertSee('Popraw mój szkic')
            ->assertSee('data-growth-ai-author-source="mail"', false)
            ->assertSee('Profesjonalny HTML maila')
            ->assertSee('Kod HTML')
            ->assertSee('aria-label="Pogrubienie"', false)
            ->assertSee('aria-label="Link"', false)
            ->assertSee('id="material_ai_html" checked', false)
            ->assertSee('Długość maila')
            ->assertSee('Krótki (ok. 150–250 słów)')
            ->assertSee('Dłuższy (ok. 300–450 słów)')
            ->assertSee('Dodaj emotikony do treści maila')
            ->assertSee($this->liveLabel())
            ->assertSee(MaterialDraftTask::LINK_PLACEHOLDER);
    }

    public function test_mail_payload_and_schema(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->mailPayload();

        $this->requestFor($user, self::MAIL)->assertSessionHas('success');

        $input = $this->provider->input;
        $this->assertSame(['material', 'campaign', 'direction', 'concept', 'source_materials', 'current_draft', 'style', 'instruction', 'mode'], array_keys($input));
        $this->assertSame('generate', $input['mode']);
        $this->assertSame(['key' => self::MAIL, 'name' => 'Mailing główny', 'type' => 'main_mail'], $input['material']);
        $this->assertSame($this->liveLabel(), $input['campaign']['live_label']);
        $this->assertSame(['youtube_description' => ''], $input['source_materials']);
        $this->assertSame(['emojis' => false, 'length' => 'short', 'html' => false, 'address_form' => 'ty', 'address_form_overridden' => false], $input['style']);
        $this->assertSame(['subject_options', 'preheader', 'body', 'change_summary'], $this->provider->schema['required']);
        $this->assertSame(MaterialDraftTask::MAIL_PROMPT_VERSION, $this->proposal(self::MAIL)['prompt_version']);
        foreach (['style.address_form', 'Zespół PNE', '[Name,fallback=]', 'style.length', 'campaign.live_label', 'NIE zaczynaj body od'] as $rule) {
            $this->assertStringContainsString($rule, $this->provider->instructions);
        }
        $this->assertStringContainsString('Address Form Policy', $this->provider->instructions);
        $this->assertStringNotContainsString(MaterialDraftTask::LINK_PLACEHOLDER, $this->provider->instructions);
    }

    public function test_main_mail_refine_sends_the_unsaved_fields_and_iterate_uses_the_proposal(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->mailPayload();
        $author = "Temat: Mój temat\nPreheader: Krótki preheader.\n\nDzień dobry,\n\nMój niezapisany mail.";

        $this->requestFor($user, self::MAIL, [
            'mode' => 'refine',
            'author_draft' => $author,
            'instruction' => 'Zostaw temat',
        ]);

        $this->assertSame('refine', $this->provider->input['mode']);
        $this->assertSame($author, $this->provider->input['author_draft']);
        $this->assertSame('', $this->provider->input['current_draft']);
        $this->assertSame('Canva AI w pracy nauczyciela', $this->provider->input['campaign']['working_topic']);
        $this->assertArrayNotHasKey('voice', $this->provider->input);

        $this->requestFor($user, self::MAIL, [
            'mode' => 'iterate',
            'instruction' => 'Skróć tylko zakończenie',
        ]);

        $this->assertSame('iterate', $this->provider->input['mode']);
        $this->assertArrayHasKey('previous_proposal', $this->provider->input);
        $this->assertSame(2, $this->provider->calls);
    }

    public function test_main_mail_html_option_formats_the_plain_body(): void
    {
        $user = $this->readyProject();
        $payload = $this->mailPayload();
        $payload['body'] = "Dzień dobry,\n\n- Pierwszy punkt\n- Drugi <b>punkt</b>\n\nZapisz się:\n[LINK DO ZAPISU]\n\nZ pozdrowieniami,\nZespół PNE";
        $this->provider->payload = $payload;

        $this->requestFor($user, self::MAIL, ['html' => '1'])->assertSessionHas('success');

        $this->assertTrue($this->provider->input['style']['html']);
        $this->assertStringContainsString('style.html', $this->provider->instructions);
        $draft = $this->proposal(self::MAIL)['draft'];
        $fields = MaterialDraftTask::parseMainMail($draft);
        $this->assertStringNotContainsString(MailHtmlFormatter::MARKER, $fields['body']);
        $this->assertStringNotContainsString('<table', $fields['body']);
        $this->assertStringContainsString('Pierwszy punkt', $fields['body']);
        $this->assertStringContainsString('Drugi <b>punkt</b>', $fields['body']);
        $this->assertSame('Praktyczny webinar dla nauczycieli.', $fields['preheader']);
        $preview = MailHtmlFormatter::format($fields['body']);
        $this->assertSame(1, substr_count($preview, 'href="'.MaterialDraftTask::LINK_PLACEHOLDER.'"'));
        $this->assertStringContainsString('Drugi &lt;b&gt;punkt&lt;/b&gt;', $preview);
        $this->assertStringNotContainsString('Zapisz się:', $preview);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertSee('Podgląd propozycji HTML', false)
            ->assertSee('data-pne-mail=', false);
    }

    public function test_mail_length_and_emojis_reach_the_model(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->mailPayload();

        $this->requestFor($user, self::MAIL, ['length' => 'long', 'emojis' => '1']);

        $this->assertSame(['emojis' => true, 'length' => 'long', 'html' => false, 'address_form' => 'ty', 'address_form_overridden' => false], $this->provider->input['style']);

        $this->actingAs($user)
            ->from(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]), ['length' => 'huge'])
            ->assertSessionHasErrors('length');
        $this->assertSame(1, $this->provider->calls);
    }

    public function test_mail_uses_only_approved_youtube_description(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->mailPayload();

        $this->saveMaterial($user, self::MATERIAL, 'REVIEW', 'Opis YouTube do sprawdzenia');
        $this->requestFor($user, self::MAIL);
        $this->assertSame('', $this->provider->input['source_materials']['youtube_description']);

        $this->saveMaterial($user, self::MATERIAL, 'APPROVED', 'Zatwierdzony opis YouTube');
        $this->requestFor($user, self::MAIL);
        $this->assertSame('Zatwierdzony opis YouTube', $this->provider->input['source_materials']['youtube_description']);
    }

    public function test_mail_draft_is_composed_from_subjects_preheader_and_body(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->mailPayload();
        $this->requestFor($user, self::MAIL);

        $this->assertSame(
            "Temat: Canva AI w pracy nauczyciela\nInne propozycje tematu:\n- Zaproszenie na webinar TIK\n- Praktyczna Canva AI w szkole\nPreheader: Praktyczny webinar dla nauczycieli.\n\n".$this->mailPayload()['body'],
            $this->proposal(self::MAIL)['draft'],
        );

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertSessionHas('success');
        $this->assertStringStartsWith('Temat: Canva AI w pracy nauczyciela', GrowthArtifact::query()->where('key', self::MAIL)->sole()->payload['draft']);
    }

    public function test_mail_subjects_and_preheader_start_with_a_capital_letter(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = array_merge($this->mailPayload(), [
            'subject_options' => ['canva ai w pracy nauczyciela', '„canva AI” w szkole', 'Już gotowe prompty'],
            'preheader' => 'praktyczny pokaz z promptami.',
        ]);

        $this->requestFor($user, self::MAIL);

        $fields = MaterialDraftTask::parseMainMail($this->proposal(self::MAIL)['draft']);
        $this->assertSame('Canva ai w pracy nauczyciela', $fields['subject']);
        $this->assertSame(['„Canva AI” w szkole', 'Już gotowe prompty'], $fields['alternatives']);
        $this->assertSame('Praktyczny pokaz z promptami.', $fields['preheader']);
    }

    public function test_mail_response_with_wrong_shape_or_unsafe_content_is_rejected(): void
    {
        $user = $this->readyProject();

        $twoSubjects = array_merge($this->mailPayload(), ['subject_options' => ['Jeden', 'Dwa']]);
        $extra = $this->mailPayload() + ['live_date' => '2030-01-01'];
        $emptyPreheader = array_merge($this->mailPayload(), ['preheader' => ' ']);
        $emptySubject = array_merge($this->mailPayload(), ['subject_options' => ['Jeden', ' ', 'Trzy']]);
        $url = array_merge($this->mailPayload(), ['body' => "Dzień dobry,\nzapisy: https://example.com/zapisy"]);

        foreach ([$twoSubjects, $extra, $emptyPreheader, $emptySubject, $url] as $payload) {
            $this->provider->payload = $payload;
            $this->requestFor($user, self::MAIL)->assertSessionHas('error', self::INVALID_MESSAGE);
            $this->assertNull($this->proposal(self::MAIL));
        }
    }

    public function test_mail_simulation_follows_length_and_emojis(): void
    {
        config()->set('growth_ai.enabled', false);
        $user = $this->readyProject();
        $this->actingAs($user)->put(route('growth.projects.concept.update', DemoTikWebinarProject::PROJECT_ID), [
            'title' => 'Canva AI w pracy nauczyciela',
            'subtitle' => 'Podtytuł',
            'promise' => 'Obietnica',
            'points' => "Punkt 1\nPunkt 2",
            'plan' => 'Intro, pokaz, pytania',
            'cta' => 'CTA',
            'lead_magnet' => 'Checklista',
            'next_product' => 'nie',
        ]);
        $this->approve($user, 'concept');

        $this->requestFor($user, self::MAIL, ['length' => 'short', 'emojis' => '0']);
        $short = $this->proposal(self::MAIL)['draft'];
        $this->assertStringStartsWith('Temat: ', $short);
        $this->assertStringContainsString('zapraszamy Państwa', $short);
        $this->assertStringNotContainsString("Dzień dobry,\n", $short);
        $shortBody = MaterialDraftTask::parseMainMail($short)['body'];
        $this->assertStringNotContainsString('Termin: '.$this->liveLabel(), $shortBody);
        $this->assertStringNotContainsString(MaterialDraftTask::LINK_PLACEHOLDER, $shortBody);
        $this->assertStringContainsString("Z pozdrowieniami,\nWaldemar Grabowski\nZespół PNE", $short);
        $this->assertStringNotContainsString('📅', $short);
        $this->assertStringNotContainsString('Plan spotkania:', $shortBody);

        $this->requestFor($user, self::MAIL, ['length' => 'long', 'emojis' => '1']);
        $long = $this->proposal(self::MAIL)['draft'];
        $this->assertStringContainsString('📌', $long);
        $this->assertStringContainsString('Plan spotkania:', $long);
        $this->assertSame(0, $this->provider->calls);
    }

    public function test_mail_page_has_separate_subject_preheader_and_body_fields(): void
    {
        $user = $this->readyProject();
        $template = DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, self::MAIL)['draft'];

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertOk()
            ->assertSee('name="mail_subject"', false)
            ->assertSee('name="mail_preheader"', false)
            ->assertSee('name="mail_body"', false)
            ->assertDontSee('name="draft"', false)
            ->assertSee('Kopiuj kod HTML preheadera')
            ->assertSee('>'.e($template).'</textarea>', false);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::FACEBOOK]))
            ->assertSee('name="draft"', false)
            ->assertDontSee('name="mail_subject"', false);
    }

    public function test_mail_fields_are_saved_as_one_labelled_draft(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]), [
                'status' => 'REVIEW',
                'mail_subject' => "  Mój temat\n",
                'mail_preheader' => 'Mój preheader',
                'mail_body' => "Dzień dobry,\n\nTreść.",
            ])
            ->assertSessionHas('success');

        $this->assertSame(
            "Temat: Mój temat\nPreheader: Mój preheader\n\nDzień dobry,\n\nTreść.",
            GrowthArtifact::query()->where('key', self::MAIL)->sole()->payload['draft'],
        );
        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertSee('value="Mój temat"', false)
            ->assertSee('value="Mój preheader"', false);
    }

    public function test_applied_ai_subjects_are_offered_until_the_mail_is_saved(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->mailPayload();
        $this->requestFor($user, self::MAIL);
        $this->actingAs($user)->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]));

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertSee('value="Canva AI w pracy nauczyciela"', false)
            ->assertSee('Propozycje tematu od AI')
            ->assertSee('data-mail-use-subject="Canva AI w pracy nauczyciela"', false)
            ->assertSee('data-mail-use-subject="Zaproszenie na webinar TIK"', false)
            ->assertSee('data-mail-use-subject="Praktyczna Canva AI w szkole"', false)
            ->assertSee('value="Praktyczny webinar dla nauczycieli."', false);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]), [
                'status' => 'REVIEW',
                'mail_subject' => 'Zaproszenie na webinar TIK',
                'mail_preheader' => 'Praktyczny webinar dla nauczycieli.',
                'mail_body' => $this->mailPayload()['body'],
            ]);

        $draft = GrowthArtifact::query()->where('key', self::MAIL)->sole()->payload['draft'];
        $this->assertStringStartsWith("Temat: Zaproszenie na webinar TIK\nPreheader: ", $draft);
        $this->assertStringNotContainsString('Inne propozycje tematu', $draft);
        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertDontSee('Propozycje tematu od AI');
    }

    /**
     * @return array<string, mixed>
     */
    private function mailPayload(): array
    {
        return [
            'subject_options' => ['Canva AI w pracy nauczyciela', 'Zaproszenie na webinar TIK', 'Praktyczna Canva AI w szkole'],
            'preheader' => 'Praktyczny webinar dla nauczycieli.',
            'body' => "Dzień dobry,\n\nzapraszamy Państwa na webinar.\n\nZapisz się:\n[LINK DO ZAPISU]\n\nZ pozdrowieniami,\nWaldemar Grabowski\nZespół PNE",
            'change_summary' => 'Przygotowano mailing główny.',
        ];
    }

    private function liveLabel(): string
    {
        return now()->addDays(7)->setTime(20, 0)->locale('pl')->translatedFormat('l, j F Y, \g\o\d\z. H:i');
    }

    public function test_reminder_page_offers_timing_length_and_both_links(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::REMINDER]))
            ->assertOk()
            ->assertSee('Poproś AI o nowy szkic', false)
            ->assertDontSee('Profesjonalny HTML maila')
            ->assertSee('Kiedy wysyłasz przypomnienie')
            ->assertSee('Dzień przed webinarem („jutro”)', false)
            ->assertSee('W dniu webinaru („dziś”)', false)
            ->assertSee('Krótki (ok. 80–150 słów)')
            ->assertSee('Dłuższy (ok. 180–280 słów)')
            ->assertSee('Widzimy się o 20!', false)
            ->assertSee('Mailing główny nie jest zatwierdzony')
            ->assertSee('id="mail_subject"', false)
            ->assertSee('id="mail_preheader"', false);
    }

    public function test_reminder_payload_and_prompt(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->mailPayload();

        $this->requestFor($user, self::REMINDER)->assertSessionHas('success');

        $input = $this->provider->input;
        $this->assertSame(['key' => self::REMINDER, 'name' => 'Mailing przypominający', 'type' => 'reminder_mail'], $input['material']);
        $this->assertSame($this->liveLabel(), $input['campaign']['live_label']);
        $this->assertSame(['youtube_description' => '', 'main_mail' => ''], $input['source_materials']);
        $this->assertSame([
            'emojis' => false,
            'length' => 'short',
            'timing' => 'same_day',
            'address_form' => 'ty',
            'address_form_overridden' => false,
        ], $input['style']);
        $this->assertSame(['subject_options', 'preheader', 'body', 'change_summary'], $this->provider->schema['required']);
        $this->assertSame(MaterialDraftTask::REMINDER_PROMPT_VERSION, $this->proposal(self::REMINDER)['prompt_version']);
        foreach (['style.timing', 'source_materials.main_mail', 'Widzimy się o 20', 'NIE zaczynaj body od „Dzień dobry,”', 'Zespół PNE'] as $rule) {
            $this->assertStringContainsString($rule, $this->provider->instructions);
        }
        foreach ([MaterialDraftTask::ROOM_LINK_PLACEHOLDER, MaterialDraftTask::LINK_PLACEHOLDER] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $this->provider->instructions);
        }
    }

    public function test_reminder_timing_and_length_reach_the_model(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->mailPayload();

        $this->requestFor($user, self::REMINDER, ['timing' => 'same_day', 'length' => 'long', 'emojis' => '1']);
        $this->assertSame(['emojis' => true, 'length' => 'long', 'timing' => 'same_day', 'address_form' => 'ty', 'address_form_overridden' => false], $this->provider->input['style']);

        $this->actingAs($user)
            ->from(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::REMINDER]))
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::REMINDER]), ['timing' => 'week_before'])
            ->assertSessionHasErrors('timing');
        $this->assertSame(1, $this->provider->calls);
    }

    public function test_reminder_uses_only_approved_main_mail_without_alternative_subjects(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->mailPayload();
        $mainMail = "Temat: Temat główny\nInne propozycje tematu:\n- Drugi temat\n- Trzeci temat\nPreheader: Krótki preheader.\n\nDzień dobry,\n\nTreść zaproszenia.";

        $this->saveMaterial($user, self::MAIL, 'REVIEW', $mainMail);
        $this->requestFor($user, self::REMINDER);
        $this->assertSame('', $this->provider->input['source_materials']['main_mail']);

        $this->saveMaterial($user, self::MAIL, 'APPROVED', $mainMail);
        $this->requestFor($user, self::REMINDER);
        $this->assertSame(
            "Temat: Temat główny\nPreheader: Krótki preheader.\n\nDzień dobry,\n\nTreść zaproszenia.",
            $this->provider->input['source_materials']['main_mail'],
        );
        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::REMINDER]))
            ->assertSee('AI użyje zatwierdzonego mailingu głównego');
    }

    public function test_reminder_proposal_is_stale_after_main_mail_changes(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->mailPayload();
        $this->requestFor($user, self::REMINDER);

        $this->saveMaterial($user, self::MAIL, 'APPROVED', "Temat: Nowy temat\n\nDzień dobry,\n\nNowa treść.");

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::REMINDER]))
            ->assertSessionHas('error', ProjectController::MATERIAL_AI_STALE_MESSAGE);
        $this->assertNull(GrowthArtifact::query()->where('key', self::REMINDER)->first());
    }

    public function test_reminder_simulation_follows_timing(): void
    {
        config()->set('growth_ai.enabled', false);
        $user = $this->readyProject();

        $this->requestFor($user, self::REMINDER, ['timing' => 'day_before']);
        $draft = $this->proposal(self::REMINDER)['draft'];
        $fields = MaterialDraftTask::parseMainMail($draft);
        $this->assertCount(2, $fields['alternatives']);
        $this->assertStringContainsString('jutro', $fields['body']);
        $this->assertStringNotContainsString(MaterialDraftTask::ROOM_LINK_PLACEHOLDER, $fields['body']);
        $this->assertStringNotContainsString(MaterialDraftTask::LINK_PLACEHOLDER, $fields['body']);
        $this->assertStringContainsString('Widzimy się o', $fields['subject']);

        $this->requestFor($user, self::REMINDER, ['timing' => 'same_day']);
        $draft = $this->proposal(self::REMINDER)['draft'];
        $this->assertStringContainsString('dziś', $draft);
        $this->assertStringContainsString('link poniżej', MaterialDraftTask::parseMainMail($draft)['body']);
        $this->assertSame(0, $this->provider->calls);
    }

    public function test_reminder_is_saved_from_separate_fields(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, self::REMINDER]), [
                'status' => 'REVIEW',
                'mail_subject' => 'Jutro webinar',
                'mail_preheader' => 'Link do pokoju w środku.',
                'mail_body' => "Dzień dobry,\n\nprzypominamy.",
            ])
            ->assertRedirect();

        $this->assertSame(
            "Temat: Jutro webinar\nPreheader: Link do pokoju w środku.\n\nDzień dobry,\n\nprzypominamy.",
            GrowthArtifact::query()->where('key', self::REMINDER)->sole()->payload['draft'],
        );
    }

    public function test_host_script_page_offers_duration_switch_with_custom_value(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::HOST_SCRIPT]))
            ->assertOk()
            ->assertSee('Poproś AI o nowy szkic')
            ->assertSee('Popraw mój szkic')
            ->assertSee('bez obecnego scenariusza')
            ->assertSee('Czas trwania webinaru')
            ->assertSee('45 minut')
            ->assertSee('90 minut')
            ->assertSee('id="material_ai_duration_60" checked', false)
            ->assertSee('name="duration_custom"', false)
            ->assertDontSee('id="material_ai_emojis"', false)
            ->assertSee('data-chatgpt-pdf="bundle"', false)
            ->assertSee('data-chatgpt-pdf="data"', false)
            ->assertSee('Ikony PDF')
            ->assertDontSee('Popraw ponownie');
    }

    public function test_host_script_chatgpt_pdf_exports_bundle_and_data_without_youtube(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, 'youtube-description', 'APPROVED', str_repeat('Opis YouTube bardzo długi. ', 80));

        $bundle = $this->actingAs($user)
            ->get(route('growth.projects.materials.ai.chatgpt-pdf', [
                DemoTikWebinarProject::PROJECT_ID,
                self::HOST_SCRIPT,
                'mode' => 'bundle',
                'duration' => '90',
            ]));
        $bundle->assertOk();
        $bundle->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $bundle->headers->get('content-disposition'));
        $this->assertStringContainsString('prompt_i_dane', (string) $bundle->headers->get('content-disposition'));

        $dataOnly = $this->actingAs($user)
            ->get(route('growth.projects.materials.ai.chatgpt-pdf', [
                DemoTikWebinarProject::PROJECT_ID,
                self::HOST_SCRIPT,
                'mode' => 'data',
                'duration' => '60',
            ]));
        $dataOnly->assertOk();
        $this->assertStringContainsString('dane_', (string) $dataOnly->headers->get('content-disposition'));

        $this->actingAs($user)
            ->get(route('growth.projects.materials.ai.chatgpt-pdf', [
                DemoTikWebinarProject::PROJECT_ID,
                'facebook-post',
                'mode' => 'bundle',
            ]))
            ->assertNotFound();
    }

    public function test_host_script_payload_and_prompt(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, self::HOST_SCRIPT, 'DRAFT', "Stary scenariusz do zignorowania\n".str_repeat('X', 200));

        $this->requestFor($user, self::HOST_SCRIPT, ['mode' => 'generate'])->assertSessionHas('success');

        $input = $this->provider->input;
        $this->assertSame(['key' => self::HOST_SCRIPT, 'name' => 'Scenariusz prowadzącego', 'type' => 'host_script'], $input['material']);
        $this->assertSame($this->liveLabel(), $input['campaign']['live_label']);
        $this->assertArrayNotHasKey('source_materials', $input);
        $this->assertSame('', $input['current_draft']);
        $this->assertSame(MaterialDraftTask::MODE_GENERATE, $input['mode']);
        $this->assertArrayNotHasKey('author_draft', $input);
        $this->assertSame(60, $input['style']['duration_minutes']);
        $this->assertSame('21:00', $input['style']['end_time']);
        $this->assertSame('ty', $input['style']['address_form']);
        $this->assertFalse($input['style']['address_form_overridden']);
        $this->assertSame(['draft', 'change_summary'], $this->provider->schema['required']);
        $this->assertSame(MaterialDraftTask::HOST_SCRIPT_PROMPT_VERSION, $this->proposal(self::HOST_SCRIPT)['prompt_version']);
        foreach (['Checklista przed startem', 'nagrywane', 'Pytanie na czat:', 'Pytania i odpowiedzi', 'Przejście:', 'style.end_time', 'direction.sell_later', 'concept.cta', 'TRYB PRACY'] as $rule) {
            $this->assertStringContainsString($rule, $this->provider->instructions);
        }
        $this->assertStringContainsString('Nie używaj opisu YouTube', $this->provider->instructions);
        $this->assertStringContainsString('"generate": napisz nowy scenariusz od zera', $this->provider->instructions);
        $this->assertStringNotContainsString('source_materials.youtube_description', $this->provider->instructions);
        $this->assertStringNotContainsString('Jeżeli current_draft nie jest pusty', $this->provider->instructions);
    }

    public function test_host_script_refine_sends_author_draft_and_ignores_saved_draft(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, self::HOST_SCRIPT, 'DRAFT', 'Zapisany scenariusz w bazie');

        $this->requestFor($user, self::HOST_SCRIPT, [
            'mode' => 'refine',
            'author_draft' => "Checklista przed startem\n- Kamera\n\n20:00–20:05 Intro",
            'duration' => '45',
        ])->assertSessionHas('success');

        $this->assertSame(MaterialDraftTask::MODE_REFINE, $this->provider->input['mode']);
        $this->assertSame("Checklista przed startem\n- Kamera\n\n20:00–20:05 Intro", $this->provider->input['author_draft']);
        $this->assertSame('', $this->provider->input['current_draft']);
        $this->assertSame(45, $this->provider->input['style']['duration_minutes']);

        $page = route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::HOST_SCRIPT]);
        $url = route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::HOST_SCRIPT]);
        $this->actingAs($user)->from($page)->post($url, ['mode' => 'refine', 'author_draft' => ''])
            ->assertSessionHas('error', ProjectController::MATERIAL_AI_REFINE_EMPTY_MESSAGE);
    }

    public function test_host_script_duration_options_and_custom_value_reach_the_model(): void
    {
        $user = $this->readyProject();

        $this->requestFor($user, self::HOST_SCRIPT, ['duration' => '90']);
        $this->assertSame(90, $this->provider->input['style']['duration_minutes']);
        $this->assertSame('21:30', $this->provider->input['style']['end_time']);

        $this->requestFor($user, self::HOST_SCRIPT, ['duration' => 'custom', 'duration_custom' => '75']);
        $this->assertSame(75, $this->provider->input['style']['duration_minutes']);
        $this->assertSame('21:15', $this->provider->input['style']['end_time']);

        $page = route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::HOST_SCRIPT]);
        $url = route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::HOST_SCRIPT]);
        $this->actingAs($user)->from($page)->post($url, ['duration' => 'custom'])->assertSessionHasErrors('duration_custom');
        $this->actingAs($user)->from($page)->post($url, ['duration' => 'custom', 'duration_custom' => '5'])->assertSessionHasErrors('duration_custom');
        $this->actingAs($user)->from($page)->post($url, ['duration' => 'custom', 'duration_custom' => '500'])->assertSessionHasErrors('duration_custom');
        $this->actingAs($user)->from($page)->post($url, ['duration' => '30'])->assertSessionHasErrors('duration');
        $this->assertSame(2, $this->provider->calls);
    }

    public function test_host_script_is_applied_as_draft(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = [
            'draft' => "Checklista przed startem\n- Dźwięk\n\n20:00–20:06 Intro\nCel: powitanie.",
            'change_summary' => 'Przygotowano scenariusz.',
        ];
        $this->requestFor($user, self::HOST_SCRIPT);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::HOST_SCRIPT]))
            ->assertSessionHas('success');

        $artifact = GrowthArtifact::query()->where('key', self::HOST_SCRIPT)->sole();
        $this->assertStringStartsWith('Checklista przed startem', $artifact->payload['draft']);
        $this->assertSame('DRAFT', $artifact->payload['status']);
        $this->assertNotEmpty($artifact->payload['ai_origin']['model'] ?? null);

        $material = collect(DemoTikWebinarProject::project()['materials'])
            ->first(fn (array $item): bool => ($item['id'] ?? null) === self::HOST_SCRIPT);
        $this->assertNotNull($material);
        $this->assertNotEmpty($material['ai_origin']['model'] ?? null);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::HOST_SCRIPT]))
            ->assertOk()
            ->assertSee('Wygenerowano:');
    }

    public function test_host_script_simulation_fills_the_whole_duration(): void
    {
        config()->set('growth_ai.enabled', false);
        $user = $this->readyProject();

        $this->requestFor($user, self::HOST_SCRIPT, ['duration' => 'custom', 'duration_custom' => '75']);
        $draft = $this->proposal(self::HOST_SCRIPT)['draft'];

        $this->assertStringContainsString('Checklista przed startem', $draft);
        $this->assertMatchesRegularExpression('/^20:00–\d{2}:\d{2} Intro$/mu', $draft);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}–21:15 Zakończenie$/mu', $draft);
        $this->assertStringContainsString('Spotkanie jest nagrywane.', $draft);
        $this->assertStringContainsString('Pytanie na czat:', $draft);
        $this->assertStringContainsString('Pytania i odpowiedzi', $draft);
        $this->assertSame(0, $this->provider->calls);
    }

    public function test_host_script_end_time_handles_midnight_and_missing_time(): void
    {
        $this->assertSame('00:30', MaterialDraftTask::hostScriptEndTime('23:30', 60));
        $this->assertSame('', MaterialDraftTask::hostScriptEndTime('', 60));
        $this->assertSame(60, MaterialDraftTask::hostScriptDuration('abc'));
        $this->assertSame(60, MaterialDraftTask::hostScriptDuration(500));
        $this->assertSame(120, MaterialDraftTask::hostScriptDuration('120'));
    }

    /**
     * @return array<string, string>
     */
    private function graphicPayload(): array
    {
        return [
            'headline' => 'Canva AI w pracy nauczyciela',
            'subtitle' => '',
            'cta' => 'Zapisz się',
            'visual_direction' => 'Granat i biel.',
            'image_prompt' => 'Biurko z laptopem.',
            'alt_text' => 'Grafika webinaru.',
            'change_summary' => 'Przygotowano brief grafiki.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requestFor(User $user, string $key, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, $key]), $data)
            ->assertRedirect(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, $key]));
    }

    private function assertStaleApply(User $user): void
    {
        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSessionHas('error', ProjectController::MATERIAL_AI_STALE_MESSAGE);

        $this->assertNotSame(self::AI_DRAFT, $this->material()['draft']);
        $this->assertNull($this->proposal());
        $this->assertNull(GrowthArtifact::query()->where('key', self::MATERIAL)->first());
        $this->assertSame(0, GrowthDecision::query()->where('type', 'like', 'material_ai_%')->count());
    }

    private function assertProviderErrorLeavesMaterial(string $message): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, self::MATERIAL, 'REVIEW', 'Opis przed błędem');

        $this->requestDraft($user)->assertSessionHas('error', $message);

        $this->assertSame('Opis przed błędem', $this->material()['draft']);
        $this->assertSame('REVIEW', $this->material()['status']);
        $this->assertSame(1, $this->artifact()->version);
        $this->assertNull($this->proposal());
    }

    private function assertAiCannotSetStatus(string $status): void
    {
        $this->provider->payload['status'] = $status;
        $user = $this->readyProject();
        $this->saveMaterial($user, self::MATERIAL, 'DRAFT', 'Opis roboczy');

        $this->requestDraft($user)->assertSessionHas('error', self::INVALID_MESSAGE);

        $this->assertSame('DRAFT', $this->material()['status']);
        $this->assertNull($this->proposal());
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

    private function readyProject(): User
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $this->approve($user, 'direction');
        $this->approve($user, 'concept');

        return $user;
    }

    private function approve(User $user, string $step): void
    {
        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, $step]))
            ->assertRedirect();
    }

    private function requestDraft(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertRedirect(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]));
    }

    private function applyDraft(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertRedirect();
    }

    private function rejectDraft(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.reject', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertRedirect();
    }

    private function saveMaterial(User $user, string $key, string $status, string $draft): void
    {
        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, $key]), [
                'status' => $status,
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
    private function proposal(string $key = self::MATERIAL): ?array
    {
        $project = DemoTikWebinarProject::project();

        return is_array($project) ? DemoTikWebinarProject::materialAiProposal($project, $key) : null;
    }

    private function artifact(): GrowthArtifact
    {
        $artifact = GrowthArtifact::query()->where('key', self::MATERIAL)->first();
        $this->assertNotNull($artifact);

        return $artifact;
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

final class FakeMaterialDraftProvider implements GrowthAiProvider
{
    public int $calls = 0;

    public ?GrowthAiException $exception = null;

    public ?string $taskType = null;

    public string $instructions = '';

    /** @var array<string, mixed> */
    public array $input = [];

    /** @var array<string, mixed> */
    public array $schema = [];

    /** @var array<string, mixed> */
    public array $payload = [
        'draft' => "Webinar „Canva AI w pracy nauczyciela” — 6 października 2026 r., godz. 20:00.\n\nPokażemy praktyczne zastosowania Canva AI w przygotowaniu materiałów.",
        'change_summary' => 'Przygotowano szkic opisu na podstawie zatwierdzonej koncepcji.',
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
        $this->taskType = $taskType;
        $this->instructions = $instructions;
        $this->input = $input;
        $this->schema = $schema;

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
