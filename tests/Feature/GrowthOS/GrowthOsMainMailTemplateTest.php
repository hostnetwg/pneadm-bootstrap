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

    public function test_main_mail_without_a_template_opens_on_the_default_and_other_materials_stay_plain(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertOk()
            ->assertSee('data-mail-editor', false)
            ->assertSee('Klasyczny PNE')
            ->assertSee('Osobisty')
            ->assertSee('Minimalny')
            ->assertSee('id="mail_template_classic" checked', false)
            ->assertSee('aria-label="Cofnij"', false)
            ->assertSee('aria-label="Ponów"', false)
            ->assertDontSee('id="mail_body_visual"', false);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::REMINDER]))
            ->assertOk()
            ->assertSee('data-mail-visual', false)
            ->assertSee('Poproś AI o szkic')
            ->assertDontSee('Klasyczny PNE')
            ->assertDontSee('name="template_key"', false);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description']))
            ->assertOk()
            ->assertDontSee('name="template_key"', false)
            ->assertDontSee('Klasyczny PNE');
    }

    public function test_template_is_saved_restored_and_does_not_change_the_mail_text(): void
    {
        $user = $this->readyProject();
        $fields = [
            'mail_subject' => 'Mój temat',
            'mail_preheader' => 'Mój preheader',
            'mail_body' => "Dzień dobry,\n\nTreść.",
        ];
        $draft = "Temat: Mój temat\nPreheader: Mój preheader\n\nDzień dobry,\n\nTreść.";

        $this->save($user, 'REVIEW', $fields, 'classic');
        $this->save($user, 'REVIEW', $fields, 'personal');

        $artifact = $this->artifact();
        $this->assertSame('personal', $artifact->payload['template_key']);
        $this->assertSame($draft, $artifact->payload['draft']);
        $this->assertSame('REVIEW', $artifact->payload['status']);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertOk()
            ->assertSee('id="mail_template_personal" checked', false)
            ->assertSee('value="Mój temat"', false)
            ->assertSee('value="Mój preheader"', false);

        $versions = $artifact->versions()->orderBy('version')->get();
        $this->assertCount(2, $versions);
        $this->assertSame('classic', $versions[0]->payload['template_key']);
        $this->assertSame('personal', $versions[1]->payload['template_key']);
        $this->assertSame($draft, $versions[0]->payload['draft']);
        $this->assertSame($draft, $versions[1]->payload['draft']);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.versions.restore', [DemoTikWebinarProject::PROJECT_ID, self::MAIL, $versions[0]->version]))
            ->assertRedirect();

        $restored = $this->artifact()->fresh();
        $this->assertSame('classic', $restored->payload['template_key']);
        $this->assertSame($draft, $restored->payload['draft']);
        $this->assertSame('DRAFT', $restored->payload['status']);
        $this->assertSame(GrowthArtifactVersion::SOURCE_RESTORE, $restored->versions()->orderByDesc('version')->first()->source);
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
        ], 'minimal');

        $fields = MaterialDraftTask::parseMainMail($this->artifact()->payload['draft']);
        $this->assertSame('Ukryty preheader.', $fields['preheader']);
        $this->assertStringNotContainsString('Ukryty preheader.', $fields['body']);
        $this->assertStringNotContainsString(MailHtmlFormatter::MARKER, $fields['body']);
        $this->assertStringNotContainsString('<script', $fields['body']);
        $this->assertStringNotContainsString('<iframe', $fields['body']);
        $this->assertStringNotContainsString('onclick', $fields['body']);
        $this->assertStringNotContainsString(MaterialDraftTask::LINK_PLACEHOLDER, $fields['body']);

        $copied = MailHtmlFormatter::copyHtml($fields['preheader'], $fields['body'], 'minimal');
        $this->assertStringContainsString('display:none', $copied);
        $this->assertStringContainsString('Ukryty preheader.', $copied);
        $this->assertSame(1, substr_count($copied, MailHtmlFormatter::MARKER));
        $this->assertSame(1, substr_count($copied, 'href="'.MaterialDraftTask::LINK_PLACEHOLDER.'"'));
        $this->assertStringContainsString('pnedu.pl', $copied);
        $this->assertStringContainsString('Treść', $copied);
    }

    public function test_refine_iterate_apply_and_reject_keep_the_template_and_plain_text(): void
    {
        $user = $this->readyProject();
        $draft = "Temat: Mój temat\nPreheader: Mój preheader\n\nDzień dobry,\n\nTreść.";
        $this->save($user, 'APPROVED', [
            'mail_subject' => 'Mój temat',
            'mail_preheader' => 'Mój preheader',
            'mail_body' => "Dzień dobry,\n\nTreść.",
        ], 'personal');
        $versionsBeforeAi = $this->artifact()->versions()->count();

        $wrapped = "Temat: Mój temat\nPreheader: Mój preheader\n\n".MailHtmlFormatter::format("Dzień dobry,\n\nTreść.");
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
        $this->assertArrayHasKey('direction', $this->provider->input);
        $this->assertArrayHasKey('concept', $this->provider->input);
        $this->assertStringNotContainsString(MailHtmlFormatter::MARKER, $this->provider->input['author_draft']);
        $this->assertStringNotContainsString('<table', $this->provider->input['author_draft']);
        $this->assertStringNotContainsString('style=', $this->provider->input['author_draft']);
        $this->assertStringNotContainsString(MailHtmlFormatter::CTA_LABEL, $this->provider->input['author_draft']);
        $this->assertStringContainsString('Temat: Mój temat', $this->provider->input['author_draft']);
        $this->assertStringContainsString('Dzień dobry,', $this->provider->input['author_draft']);
        $this->assertSame('APPROVED', $this->artifact()->payload['status']);
        $this->assertSame('personal', $this->artifact()->payload['template_key']);
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
        $this->assertStringNotContainsString(MailHtmlFormatter::MARKER, $this->provider->input['previous_proposal']);
        $this->assertSame('personal', $this->artifact()->payload['template_key']);
        $this->assertSame($versionsBeforeAi, $this->artifact()->versions()->count());

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]))
            ->assertSessionHas('success');

        $applied = $this->artifact()->fresh();
        $this->assertSame('DRAFT', $applied->payload['status']);
        $this->assertSame('personal', $applied->payload['template_key']);
        $this->assertStringStartsWith('Temat: Canva AI w pracy nauczyciela', $applied->payload['draft']);
        $this->assertStringNotContainsString(MailHtmlFormatter::MARKER, $applied->payload['draft']);
        $latest = $applied->versions()->orderByDesc('version')->first();
        $this->assertSame(GrowthArtifactVersion::SOURCE_AI_APPLY, $latest->source);
        $this->assertSame('personal', $latest->payload['template_key']);
        $this->assertSame('DRAFT', $latest->payload['status']);

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
        $this->assertSame('personal', $rejected->payload['template_key']);
        $this->assertNull($this->proposal());
    }

    /**
     * @param  array{mail_subject: string, mail_preheader: string, mail_body: string}  $fields
     */
    private function save(User $user, string $status, array $fields, string $template): void
    {
        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, self::MAIL]), [
                'status' => $status,
                'template_key' => $template,
                ...$fields,
            ])
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
            'subject_options' => ['Canva AI w pracy nauczyciela', 'Zaproszenie na webinar TIK', 'Praktyczna Canva AI w szkole'],
            'preheader' => 'Praktyczny webinar dla nauczycieli.',
            'body' => "Dzień dobry,\n\nzapraszamy Państwa na webinar.\n\nZapisz się:\n[LINK DO ZAPISU]\n\nZ pozdrowieniami,\nWaldemar Grabowski\nZespół PNE",
            'change_summary' => 'Przygotowano mailing główny.',
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

final class MainMailProvider implements GrowthAiProvider
{
    public int $calls = 0;

    /** @var array<string, mixed> */
    public array $input = [];

    /** @var array<string, mixed> */
    public array $payload = [];

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
