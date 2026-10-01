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
use App\Services\GrowthOS\AI\Tasks\ConceptRevisionTask;
use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Services\GrowthOS\GrowthSessionConceptStore;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GrowthOsMaterialAiDraftTest extends TestCase
{
    use RefreshDatabase;

    private const MATERIAL = 'youtube-description';

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

    public function test_material_page_shows_ai_action_only_for_youtube_description(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertOk()
            ->assertSee('Poproś AI o szkic')
            ->assertSee('AI: OpenAI / '.config('growth_ai.model'));

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, 'facebook-post']))
            ->assertOk()
            ->assertDontSee('Poproś AI o szkic');
    }

    public function test_other_material_key_cannot_use_the_ai_flow(): void
    {
        $user = $this->readyProject();

        foreach (['main-mail', 'facebook-post', 'landing'] as $key) {
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
        $this->assertSame(['material', 'campaign', 'direction', 'concept', 'current_draft', 'style', 'instruction'], array_keys($input));
        $this->assertSame('', $input['instruction']);
        $this->assertSame(['emojis'], array_keys($input['style']));
        $this->assertSame(['key', 'name', 'type'], array_keys($input['material']));
        $this->assertSame(['working_topic', 'goal', 'live_date', 'live_time', 'timezone', 'host_name'], array_keys($input['campaign']));
        $this->assertSame(['why_now', 'audience', 'problem', 'takeaway', 'sell_later'], array_keys($input['direction']));
        $this->assertSame(
            ['title', 'subtitle', 'promise', 'points', 'plan', 'cta', 'additional_material'],
            array_keys($input['concept']),
        );
        $this->assertSame(self::MATERIAL, $input['material']['key']);
        $this->assertSame('Canva AI w pracy nauczyciela', $input['campaign']['working_topic']);
        $this->assertSame('PDF: 7 promptów Canva AI dla nauczyciela.', $input['concept']['additional_material']);
        $this->assertArrayNotHasKey('next_product', $input['concept']);
    }

    public function test_emojis_are_requested_by_default_and_checkbox_is_checked(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSee('Dodaj emotikony do opisu')
            ->assertSee('id="material_ai_emojis" checked', false);

        $this->requestDraft($user);

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

        $this->assertSame('Waldemar Grabowski', $this->provider->input['campaign']['host_name']);
        $this->assertStringContainsString('campaign.host_name', $this->provider->instructions);
    }

    public function test_placeholder_host_is_sent_as_empty(): void
    {
        $user = $this->readyProject();
        $stored = session(DemoTikWebinarProject::SESSION_PROJECT);
        $stored['host'] = '—';
        session([DemoTikWebinarProject::SESSION_PROJECT => $stored]);
        \App\Models\GrowthOS\GrowthCampaign::query()->update(['host_name' => null]);

        $this->requestDraft($user);

        $this->assertSame('', $this->provider->input['campaign']['host_name']);
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
        $this->assertSame(['direction', 'concept', 'material', 'host'], array_keys($proposal['fingerprint']));

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
        $stored['material_ai_proposal']['fingerprint']['concept'] = hash('sha256', 'inna koncepcja');
        session([DemoTikWebinarProject::SESSION_PROJECT => $stored]);

        $this->assertStaleApply($user);
    }

    public function test_direction_change_blocks_old_apply(): void
    {
        $user = $this->readyProject();
        $this->requestDraft($user);

        $this->actingAs($user)->put(route('growth.projects.direction.update', DemoTikWebinarProject::PROJECT_ID), [
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
            ->assertSessionHas('error', 'Dzienny limit AI został wykorzystany. Możesz kontynuować ręcznie.');

        $this->assertSame(1, $this->provider->calls);
        $this->assertNull($this->proposal());
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
    private function proposal(): ?array
    {
        $project = DemoTikWebinarProject::project();

        return is_array($project) ? DemoTikWebinarProject::materialAiProposal($project, self::MATERIAL) : null;
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
