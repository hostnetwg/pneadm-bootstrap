<?php

namespace Tests\Feature\GrowthOS;

use App\Http\Controllers\GrowthOS\ProjectController;
use App\Models\GrowthOS\GrowthArtifactImage;
use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\AI\Contracts\GrowthAiImageProvider;
use App\Services\GrowthOS\AI\Data\AiImageResponse;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\GrowthImageService;
use App\Services\GrowthOS\AI\Tasks\GraphicImageTask;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GrowthOsMaterialImageTest extends TestCase
{
    use RefreshDatabase;

    private const GRAPHIC = 'main-graphic';

    private const DESCRIPTION = 'Jasne biurko nauczyciela z laptopem i kartami pracy, miękkie światło dzienne.';

    private FakeImageProvider $provider;

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
        config()->set('growth_ai.images.daily_per_user', 100);
        config()->set('growth_ai.images.disk', 'local');
        config()->set('growth_ai.circuit.failure_threshold', 100);

        Storage::fake('local');
        Http::preventStrayRequests();

        $this->provider = new FakeImageProvider;
        $this->app->instance(GrowthAiImageProvider::class, $this->provider);
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

    public function test_generator_is_shown_only_for_main_graphic(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get($this->materialUrl(self::GRAPHIC))
            ->assertOk()
            ->assertSee('Generator obrazu')
            ->assertSee('Poziomy 16:9 (1920×1080)')
            ->assertSee('Kwadrat (1080×1080)')
            ->assertSee('AI: OpenAI / gpt-image-2 · medium')
            ->assertDontSee('id="material_image_include_headline" checked', false);

        $this->actingAs($user)
            ->get($this->materialUrl('youtube-description'))
            ->assertOk()
            ->assertDontSee('Generator obrazu');

        $this->actingAs($user)
            ->post(route('growth.projects.materials.images.generate', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description']), $this->form())
            ->assertNotFound();
    }

    public function test_description_is_prefilled_from_the_saved_brief(): void
    {
        $user = $this->readyProject();
        $this->saveBrief($user);

        $this->actingAs($user)
            ->get($this->materialUrl(self::GRAPHIC))
            ->assertSee('Biurko z laptopem i kubkiem.')
            ->assertSee('Nagłówek „Canva AI w szkole”', false);
    }

    public function test_landscape_image_is_scaled_to_1920_by_1080_and_stored_privately(): void
    {
        $user = $this->readyProject();

        $this->generate($user)->assertSessionHas('success', 'Wygenerowano obraz. Sprawdź go w galerii. Nic nie opublikowano.');

        $this->assertSame(1, $this->provider->calls);
        $this->assertSame('2048x1152', $this->provider->size);
        $this->assertSame('medium', $this->provider->quality);
        $this->assertStringStartsWith(self::DESCRIPTION, $this->provider->prompt);
        $this->assertStringContainsString('Kompozycja pozioma 16:9', $this->provider->prompt);
        $this->assertStringNotContainsString('przycięte', $this->provider->prompt);
        $this->assertStringContainsString('Na obrazie nie może być żadnego tekstu', $this->provider->prompt);
        $this->assertStringContainsString('Bez wizerunku konkretnych, rozpoznawalnych osób.', $this->provider->prompt);

        $image = GrowthArtifactImage::query()->sole();
        $this->assertSame('landscape', $image->format);
        $this->assertSame([1920, 1080], [$image->width, $image->height]);
        $this->assertSame(GrowthArtifactImage::SOURCE_OPENAI, $image->source);
        $this->assertSame('gpt-image-2', $image->model);
        $this->assertSame(self::DESCRIPTION, $image->prompt);
        $this->assertFalse($image->is_selected);
        $this->assertSame(self::GRAPHIC, $image->artifact->key);
        Storage::disk('local')->assertExists($image->path);
        $this->assertSame([1920, 1080], array_slice(getimagesizefromstring(Storage::disk('local')->get($image->path)), 0, 2));
    }

    public function test_square_image_is_scaled_to_1080(): void
    {
        $user = $this->readyProject();

        $this->generate($user, ['format' => 'square']);

        $this->assertSame('1024x1024', $this->provider->size);
        $this->assertStringContainsString('Kompozycja kwadratowa', $this->provider->prompt);
        $image = GrowthArtifactImage::query()->sole();
        $this->assertSame([1080, 1080], array_slice(getimagesizefromstring(Storage::disk('local')->get($image->path)), 0, 2));
    }

    public function test_headline_and_app_date_are_added_only_when_requested(): void
    {
        $user = $this->readyProject();
        $this->saveBrief($user);

        $this->generate($user, ['include_headline' => '1']);

        $this->assertStringContainsString('duży nagłówek „Canva AI w szkole”', $this->provider->prompt);
        $this->assertStringContainsString('mniejszy napis „'.$this->liveLabel().'”', $this->provider->prompt);
        $this->assertStringNotContainsString('Na obrazie nie może być żadnego tekstu', $this->provider->prompt);
        $this->assertTrue(GrowthArtifactImage::query()->sole()->include_headline);
    }

    public function test_disabled_flag_stores_a_local_placeholder_without_provider(): void
    {
        config()->set('growth_ai.enabled', false);
        $user = $this->readyProject();

        $this->generate($user)->assertSessionHas('success', 'Przygotowano obraz zastępczy (symulacja lokalna, bez wywołania OpenAI).');

        $this->assertSame(0, $this->provider->calls);
        $image = GrowthArtifactImage::query()->sole();
        $this->assertSame(GrowthArtifactImage::SOURCE_SIMULATION, $image->source);
        $this->assertNull($image->model);
        $this->assertSame([1920, 1080], array_slice(getimagesizefromstring(Storage::disk('local')->get($image->path)), 0, 2));

        $this->actingAs($user)->get($this->materialUrl(self::GRAPHIC))->assertSee('Symulacja');
    }

    public function test_unapproved_concept_blocks_generation(): void
    {
        $user = $this->superAdmin();
        $this->createProject($user);
        $this->approve($user, 'direction');

        $this->generate($user)->assertSessionHas('error', ProjectController::MATERIAL_AI_PRECONDITION_MESSAGE);

        $this->assertSame(0, $this->provider->calls);
        $this->assertSame(0, GrowthArtifactImage::query()->count());
    }

    public function test_invalid_input_is_rejected_before_the_provider(): void
    {
        $user = $this->readyProject();

        $this->generate($user, ['format' => 'portrait'])->assertSessionHasErrors('format');
        $this->generate($user, ['image_prompt' => ''])->assertSessionHasErrors('image_prompt');
        $this->generate($user, ['image_prompt' => str_repeat('a', GraphicImageTask::MAX_PROMPT_CHARS + 1)])->assertSessionHasErrors('image_prompt');
        $this->generate($user, ['image_prompt' => 'Zdjęcie, kontakt jan.kowalski@example.com'])
            ->assertSessionHas('error', GrowthAiException::dataPolicyViolation()->userMessage);
        $this->generate($user, ['image_prompt' => 'Grafika jak na https://example.com'])
            ->assertSessionHas('error', GrowthAiException::dataPolicyViolation()->userMessage);

        $this->assertSame(0, $this->provider->calls);
        $this->assertSame(0, GrowthArtifactImage::query()->count());
    }

    public function test_daily_image_limit_blocks_the_next_request(): void
    {
        config()->set('growth_ai.images.daily_per_user', 2);
        $user = $this->readyProject();

        $this->generate($user)->assertSessionHas('success');
        $this->generate($user)->assertSessionHas('success');
        $this->generate($user)->assertSessionHas('error', GrowthImageService::DAILY_LIMIT_MESSAGE);

        $this->assertSame(2, $this->provider->calls);
        $this->assertSame(2, GrowthArtifactImage::query()->count());
    }

    public function test_provider_error_and_broken_image_store_nothing(): void
    {
        $user = $this->readyProject();

        $this->provider->exception = GrowthAiException::unavailable('provider_server_error');
        $this->generate($user)->assertSessionHas('error', 'AI jest chwilowo niedostępne. Możesz kontynuować ręcznie.');

        $this->provider->exception = null;
        $this->provider->bytes = 'not an image';
        $this->generate($user)->assertSessionHas('error', GraphicImageTask::INVALID_IMAGE_MESSAGE);

        $this->assertSame(0, GrowthArtifactImage::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_non_super_admin_cannot_generate_images(): void
    {
        $this->readyProject();
        $other = User::factory()->create(['is_active' => true]);

        $this->generate($other)->assertForbidden();

        $this->assertSame(0, $this->provider->calls);
        $this->assertSame(0, GrowthArtifactImage::query()->count());
    }

    public function test_gallery_keeps_latest_ten_and_never_drops_the_selected_image(): void
    {
        $user = $this->readyProject();

        $this->generate($user);
        $first = GrowthArtifactImage::query()->sole();
        $this->actingAs($user)
            ->post(route('growth.projects.materials.images.select', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC, $first->id]))
            ->assertSessionHas('success');

        for ($i = 0; $i < GrowthArtifactImage::KEEP_LATEST; $i++) {
            $this->generate($user);
        }

        $this->assertSame(GrowthArtifactImage::KEEP_LATEST, GrowthArtifactImage::query()->count());
        $this->assertTrue($first->fresh()->is_selected);
        $this->assertCount(GrowthArtifactImage::KEEP_LATEST, Storage::disk('local')->allFiles());
    }

    public function test_select_keeps_a_single_main_image(): void
    {
        $user = $this->readyProject();
        $this->generate($user);
        $this->generate($user);
        [$older, $newer] = GrowthArtifactImage::query()->orderBy('id')->get()->all();

        foreach ([$older, $newer] as $image) {
            $this->actingAs($user)
                ->post(route('growth.projects.materials.images.select', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC, $image->id]));
        }

        $this->assertFalse($older->fresh()->is_selected);
        $this->assertTrue($newer->fresh()->is_selected);
        $this->actingAs($user)->get($this->materialUrl(self::GRAPHIC))->assertSee('✓ Grafika główna');
    }

    public function test_image_can_be_previewed_downloaded_and_deleted(): void
    {
        $user = $this->readyProject();
        $this->generate($user);
        $image = GrowthArtifactImage::query()->sole();
        $url = route('growth.projects.materials.images.show', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC, $image->id]);

        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($user)->get($url.'?download=1')
            ->assertOk()
            ->assertDownload($image->downloadName());

        $this->actingAs($user)
            ->delete(route('growth.projects.materials.images.delete', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC, $image->id]))
            ->assertSessionHas('success', 'Usunięto obraz z galerii.');

        $this->assertSame(0, GrowthArtifactImage::query()->count());
        Storage::disk('local')->assertMissing($image->path);
        $this->actingAs($user)->get($url)->assertNotFound();
    }

    public function test_image_is_not_reachable_through_another_material_or_guest(): void
    {
        $user = $this->readyProject();
        $this->generate($user);
        $image = GrowthArtifactImage::query()->sole();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.images.show', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description', $image->id]))
            ->assertNotFound();
        $this->actingAs($user)
            ->delete(route('growth.projects.materials.images.delete', [DemoTikWebinarProject::PROJECT_ID, 'youtube-description', $image->id]))
            ->assertNotFound();

        auth()->logout();
        $this->get(route('growth.projects.materials.images.show', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC, $image->id]))
            ->assertRedirect();
        $this->assertSame(1, GrowthArtifactImage::query()->count());
    }

    public function test_log_has_cost_and_size_but_no_prompt(): void
    {
        $this->logPath = storage_path('logs/growth-ai-image-test-'.uniqid().'.log');
        config()->set('logging.channels.growth_ai_image_test', [
            'driver' => 'single',
            'path' => $this->logPath,
            'level' => 'debug',
        ]);
        config()->set('growth_ai.log_channel', 'growth_ai_image_test');
        $user = $this->readyProject();

        $this->generate($user);

        $log = (string) file_get_contents($this->logPath);
        $this->assertStringContainsString('"task_type":"'.GraphicImageTask::TYPE.'"', $log);
        $this->assertStringContainsString('"image_kind":"landscape"', $log);
        $this->assertStringContainsString('"size":"2048x1152"', $log);
        $this->assertStringContainsString('"estimated_cost_usd":0.042', $log);
        $this->assertStringNotContainsString('biurko', mb_strtolower($log));
    }

    public function test_reset_button_clears_the_daily_limit(): void
    {
        config()->set('growth_ai.images.daily_per_user', 1);
        $this->logPath = storage_path('logs/growth-ai-image-test-'.uniqid().'.log');
        config()->set('logging.channels.growth_ai_image_test', [
            'driver' => 'single',
            'path' => $this->logPath,
            'level' => 'debug',
        ]);
        config()->set('growth_ai.log_channel', 'growth_ai_image_test');
        $user = $this->readyProject();

        $this->actingAs($user)->get($this->materialUrl(self::GRAPHIC))
            ->assertSee('wykorzystano 0 z 1 obrazów')
            ->assertSee('Zresetuj limit');
        $this->generate($user)->assertSessionHas('success');
        $this->generate($user)->assertSessionHas('error', GrowthImageService::DAILY_LIMIT_MESSAGE);
        $this->actingAs($user)->get($this->materialUrl(self::GRAPHIC))->assertSee('wykorzystano 1 z 1 obrazów');

        $this->actingAs($user)
            ->post(route('growth.projects.materials.images.limit.reset', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC]))
            ->assertRedirect($this->materialUrl(self::GRAPHIC))
            ->assertSessionHas('success', 'Zresetowano dzienny limit obrazów AI. Możesz znów generować obrazy.');

        $this->actingAs($user)->get($this->materialUrl(self::GRAPHIC))->assertSee('wykorzystano 0 z 1 obrazów');
        $this->generate($user)->assertSessionHas('success');
        $this->assertSame(2, $this->provider->calls);
        $this->assertStringContainsString('"used_before_reset":1', (string) file_get_contents($this->logPath));
    }

    public function test_reset_limit_is_only_for_super_admin_and_main_graphic(): void
    {
        $user = $this->readyProject();
        $other = User::factory()->create(['is_active' => true]);

        $this->actingAs($other)
            ->post(route('growth.projects.materials.images.limit.reset', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC]))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('growth.projects.materials.images.limit.reset', [DemoTikWebinarProject::PROJECT_ID, 'facebook-post']))
            ->assertNotFound();
    }

    public function test_legacy_model_uses_supported_size_and_crop_hint(): void
    {
        $this->provider->model = 'gpt-image-1';
        $user = $this->readyProject();

        $this->generate($user);

        $this->assertSame('1536x1024', $this->provider->size);
        $this->assertStringContainsString('górny i dolny brzeg zostaną lekko przycięte', $this->provider->prompt);
        $this->assertSame([1920, 1080], array_slice(getimagesizefromstring(Storage::disk('local')->get(GrowthArtifactImage::query()->sole()->path)), 0, 2));
    }

    public function test_square_version_relays_out_the_landscape_image_through_edit(): void
    {
        $user = $this->readyProject();
        $this->generate($user);
        $landscape = GrowthArtifactImage::query()->sole();

        $this->adapt($user, $landscape)
            ->assertSessionHas('success', 'Utworzono wersję kwadratową z obrazu poziomego. Sprawdź, czy wszystkie elementy się zmieściły.');

        $this->assertSame('edit', $this->provider->operation);
        $this->assertSame('1024x1024', $this->provider->size);
        $this->assertSame(Storage::disk('local')->get($landscape->path), $this->provider->sourceBytes);
        $this->assertSame('image/jpeg', $this->provider->sourceMime);
        $this->assertStringContainsString('Przekomponuj tę grafikę do formatu kwadratowego 1:1', $this->provider->prompt);
        $this->assertStringContainsString('Nie przycinaj ani nie zniekształcaj elementów', $this->provider->prompt);
        $this->assertStringContainsString(self::DESCRIPTION, $this->provider->prompt);
        $this->assertStringContainsString('Na obrazie nie może być żadnego tekstu', $this->provider->prompt);

        $square = GrowthArtifactImage::query()->where('format', 'square')->sole();
        $this->assertSame($landscape->id, $square->source_image_id);
        $this->assertSame(GraphicImageTask::ADAPT_PROMPT_VERSION, $square->prompt_version);
        $this->assertSame([1080, 1080], array_slice(getimagesizefromstring(Storage::disk('local')->get($square->path)), 0, 2));
        $this->actingAs($user)->get($this->materialUrl(self::GRAPHIC))->assertSee('Kwadrat z poziomego #'.$landscape->id);
    }

    public function test_square_version_keeps_the_text_of_the_source_image(): void
    {
        $user = $this->readyProject();
        $this->saveBrief($user);
        $this->generate($user, ['include_headline' => '1']);
        $landscape = GrowthArtifactImage::query()->sole();
        $this->assertSame('Canva AI w szkole', $landscape->overlay_headline);
        $this->assertSame($this->liveLabel(), $landscape->overlay_date);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC]), [
                'status' => 'REVIEW',
                'draft' => 'Nagłówek: Zupełnie inny nagłówek',
            ]);
        $this->adapt($user, $landscape);

        $this->assertStringContainsString('przeniesione tak, żeby były w całości czytelne w kwadracie', $this->provider->prompt);
        $this->assertStringContainsString('„Canva AI w szkole”', $this->provider->prompt);
        $this->assertStringContainsString('„'.$this->liveLabel().'”', $this->provider->prompt);
        $this->assertStringNotContainsString('Zupełnie inny nagłówek', $this->provider->prompt);
        $this->assertTrue(GrowthArtifactImage::query()->where('format', 'square')->sole()->include_headline);
    }

    public function test_square_version_needs_a_landscape_source_and_counts_to_the_limit(): void
    {
        config()->set('growth_ai.images.daily_per_user', 2);
        $user = $this->readyProject();
        $this->generate($user, ['format' => 'square']);
        $square = GrowthArtifactImage::query()->sole();

        $this->adapt($user, $square)->assertSessionHas('error', 'Wersję kwadratową można utworzyć tylko z obrazu poziomego.');
        $this->assertSame(1, $this->provider->calls);

        $this->generate($user);
        $landscape = GrowthArtifactImage::query()->where('format', 'landscape')->sole();
        $this->adapt($user, $landscape)->assertSessionHas('error', GrowthImageService::DAILY_LIMIT_MESSAGE);
        $this->assertSame(2, $this->provider->calls);
    }

    public function test_square_version_in_simulation_does_not_call_the_provider(): void
    {
        config()->set('growth_ai.enabled', false);
        $user = $this->readyProject();
        $this->generate($user);
        $landscape = GrowthArtifactImage::query()->sole();

        $this->adapt($user, $landscape)
            ->assertSessionHas('success', 'Przygotowano kwadratowy obraz zastępczy (symulacja lokalna, bez wywołania OpenAI).');

        $this->assertSame(0, $this->provider->calls);
        $this->assertSame($landscape->id, GrowthArtifactImage::query()->where('format', 'square')->sole()->source_image_id);
    }

    public function test_square_button_is_shown_only_for_landscape_images(): void
    {
        $user = $this->readyProject();
        $this->generate($user, ['format' => 'square']);
        $this->actingAs($user)->get($this->materialUrl(self::GRAPHIC))->assertDontSee('Utwórz wersję kwadratową');

        $this->generate($user);
        $this->actingAs($user)->get($this->materialUrl(self::GRAPHIC))->assertSee('Utwórz wersję kwadratową');
    }

    private function adapt(User $user, GrowthArtifactImage $image): TestResponse
    {
        return $this->actingAs($user)
            ->post(route('growth.projects.materials.images.square', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC, $image->id]));
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'format' => 'landscape',
            'image_prompt' => self::DESCRIPTION,
            'include_headline' => '0',
        ], $overrides);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function generate(User $user, array $overrides = []): TestResponse
    {
        return $this->actingAs($user)
            ->post(route('growth.projects.materials.images.generate', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC]), $this->form($overrides));
    }

    private function saveBrief(User $user): void
    {
        $brief = "Formaty: 16:9 (1920×1080) i kwadrat (1080×1080)\nNagłówek: Canva AI w szkole\nTermin: ".$this->liveLabel()
            ."\n\nKierunek wizualny:\nGranat i biel."
            ."\n\nOpis obrazu dla AI (bez tekstu na obrazie):\nBiurko z laptopem i kubkiem."
            ."\n\nTekst alternatywny (alt):\nGrafika webinaru.";

        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, self::GRAPHIC]), [
                'status' => 'REVIEW',
                'draft' => $brief,
            ])
            ->assertRedirect();
    }

    private function materialUrl(string $key): string
    {
        return route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, $key]);
    }

    private function liveLabel(): string
    {
        return now()->addDays(7)->setTime(20, 0)->locale('pl')->translatedFormat('l, j F Y, \g\o\d\z. H:i');
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

final class FakeImageProvider implements GrowthAiImageProvider
{
    public int $calls = 0;

    public ?GrowthAiException $exception = null;

    public ?string $bytes = null;

    public string $prompt = '';

    public string $size = '';

    public string $quality = '';

    public string $model = 'gpt-image-2';

    public string $operation = '';

    public string $sourceBytes = '';

    public string $sourceMime = '';

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function generateImage(string $prompt, string $size, string $quality): AiImageResponse
    {
        $this->operation = 'generate';

        return $this->respond($prompt, $size, $quality);
    }

    public function editImage(string $prompt, string $sourceBytes, string $sourceMime, string $size, string $quality): AiImageResponse
    {
        $this->operation = 'edit';
        $this->sourceBytes = $sourceBytes;
        $this->sourceMime = $sourceMime;

        return $this->respond($prompt, $size, $quality);
    }

    private function respond(string $prompt, string $size, string $quality): AiImageResponse
    {
        $this->calls++;
        $this->prompt = $prompt;
        $this->size = $size;
        $this->quality = $quality;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return new AiImageResponse(
            bytes: $this->bytes ?? $this->png($size),
            provider: $this->name(),
            model: $this->model(),
            requestId: 'test-image-request',
            latencyMs: 10,
        );
    }

    private function png(string $size): string
    {
        [$width, $height] = array_map('intval', explode('x', $size));
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 20, 80, 160));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
