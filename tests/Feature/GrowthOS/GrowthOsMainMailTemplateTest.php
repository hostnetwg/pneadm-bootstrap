<?php

namespace Tests\Feature\GrowthOS;

use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthArtifactVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\AI\Contracts\GrowthAiProvider;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Support\GrowthOS\DemoTikWebinarProject;
use App\Support\GrowthOS\MailHtmlFormatter;
use App\Support\GrowthOS\MailTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GrowthOsMainMailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const MAIL = 'main-mail';

    private const REMINDER = 'reminder-mail';

    private MainMailProvider $provider;

    private int $outputBufferLevel = 0;

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

        $this->provider = new MainMailProvider;
        $this->app->instance(GrowthAiProvider::class, $this->provider);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_main_and_reminder_mail_use_sendy_pne_tiptap_editor(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertOk()
            ->assertSee('data-mail-editor', false)
            ->assertSee('Sendy PNE')
            ->assertSee('Dołącz ofertę płatnych szkoleń')
            ->assertSee('Pokaż informację o bezpłatnym zaświadczeniu')
            ->assertDontSee('Klasyczny PNE')
            ->assertDontSee('name="template_key"', false)
            ->assertSee('aria-label="Cofnij"', false)
            ->assertDontSee('id="mail_body_visual"', false);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::REMINDER]))
            ->assertOk()
            ->assertSee('data-mail-editor', false)
            ->assertSee('czerwony pasek przypomnienia')
            ->assertSee('Poproś AI o nowy szkic', false)
            ->assertSee('Sendy PNE')
            ->assertSee('Dołącz ofertę płatnych szkoleń')
            ->assertDontSee('data-mail-visual', false)
            ->assertDontSee('Klasyczny PNE')
            ->assertDontSee('name="template_key"', false);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description']))
            ->assertOk()
            ->assertDontSee('name="template_key"', false)
            ->assertDontSee('Sendy PNE');
    }

    public function test_certificate_and_paid_offer_flags_are_versioned(): void
    {
        $user = $this->readyProject();
        $fields = [
            'mail_subject' => 'Mój temat',
            'mail_preheader' => 'Mój preheader',
            'mail_body' => 'zapraszamy Państwa.',
        ];
        $draft = "Temat: Mój temat\nPreheader: Mój preheader\n\nzapraszamy Państwa.";

        $this->save($user, 'REVIEW', $fields, includePaid: false, showCertificate: false);
        $this->save($user, 'REVIEW', $fields, includePaid: true, showCertificate: true);

        $artifact = $this->artifact();
        $this->assertSame(MailTemplates::CANONICAL, $artifact->payload['template_key']);
        $this->assertTrue($artifact->payload['include_paid_offer']);
        $this->assertTrue($artifact->payload['show_certificate']);
        $this->assertSame($draft, $artifact->payload['draft']);
        $this->assertIsArray($artifact->payload['paid_offer_snapshot'] ?? null);

        $versions = $artifact->versions()->orderBy('version')->get();
        $this->assertGreaterThanOrEqual(2, $versions->count());
        $this->assertFalse((bool) $versions[0]->payload['include_paid_offer']);
        $this->assertTrue((bool) $versions->last()->payload['include_paid_offer']);
        $this->assertTrue((bool) $versions->last()->payload['show_certificate']);
    }

    public function test_saved_content_cannot_replace_the_wrapper_button_or_preheader(): void
    {
        $user = $this->readyProject();
        $hostile = '<table '.MailHtmlFormatter::MARKER.'><tr><td data-pne-mail-body="1"><p onclick="evil()">Treść</p>'
            .'<a href="'.MaterialDraftTask::LINK_PLACEHOLDER.'">Zapisz się na webinar</a>'
            .'<script>alert(1)</script><iframe src="https://evil.test"></iframe></td></tr></table>';

        $this->save($user, 'DRAFT', [
            'mail_subject' => 'Temat',
            'mail_preheader' => 'Ukryty preheader.',
            'mail_body' => $hostile,
        ]);

        $fields = MaterialDraftTask::parseMainMail($this->artifact()->payload['draft']);
        $this->assertSame('Ukryty preheader.', $fields['preheader']);
        $this->assertStringNotContainsString('Ukryty preheader.', $fields['body']);
        $this->assertStringNotContainsString(MailHtmlFormatter::MARKER, $fields['body']);
        $this->assertStringNotContainsString('<script', $fields['body']);
        $this->assertStringNotContainsString('<iframe', $fields['body']);
        $this->assertStringNotContainsString('onclick', $fields['body']);
        $this->assertStringNotContainsString(MaterialDraftTask::LINK_PLACEHOLDER, $fields['body']);

        $copied = MailHtmlFormatter::copyHtml($fields['preheader'], $fields['body'], MailTemplates::CANONICAL);
        $this->assertStringContainsString('display:none', $copied);
        $this->assertStringContainsString('Ukryty preheader.', $copied);
        $this->assertSame(1, substr_count($copied, MailHtmlFormatter::MARKER));
        $this->assertStringContainsString(MailHtmlFormatter::GREETING, $copied);
        $this->assertStringContainsString('Treść', $copied);
    }

    public function test_refine_iterate_apply_and_reject_keep_sendy_layout_and_plain_text(): void
    {
        $user = $this->readyProject();
        $draft = "Temat: Mój temat\nPreheader: Mój preheader\n\nzapraszamy Państwa.";
        $this->save($user, 'APPROVED', [
            'mail_subject' => 'Mój temat',
            'mail_preheader' => 'Mój preheader',
            'mail_body' => 'zapraszamy Państwa.',
        ], includePaid: false, showCertificate: true);
        $versionsBeforeAi = $this->artifact()->versions()->count();

        $wrapped = "Temat: Mój temat\nPreheader: Mój preheader\n\n".MailHtmlFormatter::format('zapraszamy Państwa.');
        $this->provider->payload = $this->mailPayload();
        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]), [
                'mode' => 'refine',
                'author_draft' => $wrapped,
                'html' => '1',
                'length' => 'short',
            ])
            ->assertRedirect();

        $this->assertSame('refine', $this->provider->input['mode']);
        $this->assertStringNotContainsString(MailHtmlFormatter::MARKER, $this->provider->input['author_draft']);
        $this->assertStringNotContainsString('<table', $this->provider->input['author_draft']);
        $this->assertStringNotContainsString(MailHtmlFormatter::CTA_LABEL, $this->provider->input['author_draft']);
        $this->assertSame(MailTemplates::CANONICAL, $this->artifact()->payload['template_key']);
        $this->assertTrue($this->artifact()->payload['show_certificate']);
        $this->assertSame($draft, $this->artifact()->payload['draft']);
        $this->assertSame($versionsBeforeAi, $this->artifact()->versions()->count());

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]), [
                'mode' => 'iterate',
                'instruction' => 'Skróć zakończenie',
            ])
            ->assertRedirect();

        $this->assertSame('iterate', $this->provider->input['mode']);
        $this->assertStringNotContainsString('<table', $this->provider->input['previous_proposal']);
        $this->assertSame($versionsBeforeAi, $this->artifact()->versions()->count());

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertSessionHas('success');

        $applied = $this->artifact()->fresh();
        $this->assertSame('DRAFT', $applied->payload['status']);
        $this->assertSame(MailTemplates::CANONICAL, $applied->payload['template_key']);
        $this->assertTrue($applied->payload['show_certificate']);
        $this->assertStringStartsWith('Temat: Canva AI w pracy nauczyciela', $applied->payload['draft']);
        $this->assertStringNotContainsString(MailHtmlFormatter::MARKER, $applied->payload['draft']);
        $latest = $applied->versions()->orderByDesc('version')->first();
        $this->assertSame(GrowthArtifactVersion::SOURCE_AI_APPLY, $latest->source);
        $this->assertSame(MailTemplates::CANONICAL, $latest->payload['template_key']);

        $afterApply = $applied->payload['draft'];
        $this->provider->payload = $this->mailPayload();
        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]), [
                'mode' => 'generate',
                'html' => '1',
            ])
            ->assertRedirect();
        $this->assertSame('generate', $this->provider->input['mode']);
        $this->assertStringNotContainsString(MailHtmlFormatter::MARKER, $this->proposal()['draft']);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.reject', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertSessionHas('success');

        $rejected = $this->artifact()->fresh();
        $this->assertSame($afterApply, $rejected->payload['draft']);
        $this->assertSame(MailTemplates::CANONICAL, $rejected->payload['template_key']);
        $this->assertNull($this->proposal());
    }

    public function test_ai_proposal_prefills_editable_mail_fields_and_save_closes_it(): void
    {
        $user = $this->readyProject();
        $this->provider->payload = $this->mailPayload();

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]), [
                'mode' => 'generate',
                'length' => 'short',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($this->proposal());

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertOk()
            ->assertSee('value="Canva AI w pracy nauczyciela"', false)
            ->assertSee('value="Zapraszamy na praktyczny webinar."', false)
            ->assertSee('zapraszamy Państwa na webinar.')
            ->assertSee('W polach poniżej jest')
            ->assertSee('Zastosuj oryginalną propozycję');

        $this->save($user, 'DRAFT', [
            'mail_subject' => 'Canva AI — poprawiony temat',
            'mail_preheader' => 'Zapraszamy na praktyczny webinar.',
            'mail_body' => 'zapraszamy Państwa na webinar — poprawiona treść.',
        ]);

        $this->assertNull($this->proposal());
        $fields = MaterialDraftTask::parseMainMail($this->artifact()->payload['draft']);
        $this->assertSame('Canva AI — poprawiony temat', $fields['subject']);
        $this->assertSame('zapraszamy Państwa na webinar — poprawiona treść.', $fields['body']);
        $this->assertContains('Webinar Canva AI dla szkoły', $fields['alternatives']);
    }

    public function test_project_links_save_and_reject_dangerous_urls(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->put(route('growth.projects.links.update', DemoTikWebinarProject::PROJECT_ID), [
                'registration_url' => 'https://pnedu.pl/courses/577',
                'youtube_live_url' => 'https://www.youtube.com/live/31GX_yG5KDo',
            ])
            ->assertRedirect();

        $project = DemoTikWebinarProject::requireProject(DemoTikWebinarProject::PROJECT_ID);
        $this->assertSame('https://pnedu.pl/courses/577', $project['registration_url']);
        $this->assertSame('https://www.youtube.com/live/31GX_yG5KDo', $project['youtube_live_url']);

        $this->actingAs($user)
            ->from(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->put(route('growth.projects.links.update', DemoTikWebinarProject::PROJECT_ID), [
                'registration_url' => 'javascript:alert(1)',
                'youtube_live_url' => 'https://evil.test/watch',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['registration_url', 'youtube_live_url']);
    }

    public function test_facebook_post_shows_project_registration_url_instead_of_placeholder(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->put(route('growth.projects.links.update', DemoTikWebinarProject::PROJECT_ID), [
                'registration_url' => 'https://pnedu.pl/courses/577',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, 'facebook-post']), [
                'status' => 'DRAFT',
                'draft' => "Zapraszamy.\n\nZapisz się: ".MaterialDraftTask::LINK_PLACEHOLDER,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, 'facebook-post']))
            ->assertOk()
            ->assertSee('https://pnedu.pl/courses/577')
            ->assertDontSee('Zapisz się: '.MaterialDraftTask::LINK_PLACEHOLDER);

        $artifact = GrowthArtifact::query()->where('key', 'facebook-post')->first();
        $this->assertNotNull($artifact);
        $this->assertStringContainsString('https://pnedu.pl/courses/577', (string) $artifact->payload['draft']);
        $this->assertStringNotContainsString(MaterialDraftTask::LINK_PLACEHOLDER, (string) $artifact->payload['draft']);
    }

    /**
     * @param  array{mail_subject: string, mail_preheader: string, mail_body: string}  $fields
     */
    private function save(
        User $user,
        string $status,
        array $fields,
        bool $includePaid = false,
        bool $showCertificate = false,
    ): void {
        $payload = [
            'status' => $status,
            ...$fields,
        ];
        if ($includePaid) {
            $payload['include_paid_offer'] = '1';
        }
        if ($showCertificate) {
            $payload['show_certificate'] = '1';
        }

        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]), $payload)
            ->assertRedirect();
    }

    private function artifact(): GrowthArtifact
    {
        return GrowthArtifact::query()->where('key', self::MAIL)->sole();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function proposal(): ?array
    {
        $project = DemoTikWebinarProject::project();

        return is_array($project) ? DemoTikWebinarProject::materialAiProposal($project, self::MAIL) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mailPayload(): array
    {
        return [
            'subject_options' => [
                'Canva AI w pracy nauczyciela',
                'Webinar Canva AI dla szkoły',
                'Praktyczny Canva AI',
            ],
            'preheader' => 'Zapraszamy na praktyczny webinar.',
            'body' => "zapraszamy Państwa na webinar.\n\n- Punkt A\n- Punkt B\n\nZ pozdrowieniami,\nZespół PNE",
            'change_summary' => 'Przygotowano szkic maila.',
        ];
    }

    private function readyProject(): User
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
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('growth.projects.store'), [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => 'Canva AI w pracy nauczyciela',
        ]);
        foreach (['direction', 'concept'] as $step) {
            $this->actingAs($user)
                ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, $step]))
                ->assertRedirect();
        }

        return $user;
    }
}

class MainMailProvider implements GrowthAiProvider
{
    /** @var array<string, mixed> */
    public array $payload = [];

    /** @var array<string, mixed> */
    public array $input = [];

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
        $this->input = $input;

        return new AiProviderResponse(
            payload: $this->payload,
            provider: 'openai',
            model: 'test-model',
            requestId: 'test',
            inputTokens: 1,
            outputTokens: 1,
            latencyMs: 1,
        );
    }
}
