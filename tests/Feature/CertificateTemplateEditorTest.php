<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Models\User;
use App\Services\Certificate\TemplateRenderer;
use App\Services\TemplateBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CertificateTemplateEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();

        $this->operator = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->partialMock(TemplateBuilderService::class, function ($mock) {
            $mock->shouldReceive('generateBladeFile')->andReturn('test.blade.php');
        });
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }
        parent::tearDown();
    }

    public function test_guest_cannot_use_gallery_endpoints(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificates/logos/logo.png', 'x');
        Storage::disk('public')->put('certificates/backgrounds/bg.png', 'x');

        $this->postJson('/api/admin/certificate-templates/upload-logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertUnauthorized();
        $this->postJson('/api/admin/certificate-templates/upload-background', [
            'background' => UploadedFile::fake()->image('bg.png'),
        ])->assertUnauthorized();
        $this->deleteJson('/api/admin/certificate-templates/delete-logo', [
            'path' => 'certificates/logos/logo.png',
        ])->assertUnauthorized();
        $this->deleteJson('/api/admin/certificate-templates/delete-background', [
            'path' => 'certificates/backgrounds/bg.png',
        ])->assertUnauthorized();

        Storage::disk('public')->assertExists('certificates/logos/logo.png');
        Storage::disk('public')->assertExists('certificates/backgrounds/bg.png');
        $this->assertCount(1, Storage::disk('public')->files('certificates/logos'));
        $this->assertCount(1, Storage::disk('public')->files('certificates/backgrounds'));
    }

    public function test_logged_in_user_can_upload_and_delete_logo(): void
    {
        Storage::fake('public');

        $path = $this->actingAs($this->operator)
            ->postJson('/api/admin/certificate-templates/upload-logo', [
                'logo' => UploadedFile::fake()->image('Logo PNE.png'),
            ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->json('path');

        $this->assertStringStartsWith('certificates/logos/', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->operator)
            ->deleteJson('/api/admin/certificate-templates/delete-logo', ['path' => $path])
            ->assertOk()
            ->assertJson(['success' => true]);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_logged_in_user_can_upload_and_delete_background(): void
    {
        Storage::fake('public');

        $path = $this->actingAs($this->operator)
            ->postJson('/api/admin/certificate-templates/upload-background', [
                'background' => UploadedFile::fake()->image('gilosz.png'),
            ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->json('path');

        $this->assertStringStartsWith('certificates/backgrounds/', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->operator)
            ->deleteJson('/api/admin/certificate-templates/delete-background', ['path' => 'storage/'.$path])
            ->assertOk();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_delete_rejects_paths_outside_gallery_directory(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $disk->put('secret.txt', 'x');
        $disk->put('certificates/backgrounds/bg.png', 'x');
        $disk->put('certificates/1/1-1-2026-PNE.pdf', 'x');

        $paths = [
            '../secret.txt',
            '../../.env',
            'certificates/logos/../../secret.txt',
            'certificates/logos/../backgrounds/bg.png',
            'certificates/logos/..',
            'certificates/logos/',
            'certificates/1/1-1-2026-PNE.pdf',
            '/etc/passwd',
            'secret.txt',
        ];

        foreach ($paths as $path) {
            $this->actingAs($this->operator)
                ->deleteJson('/api/admin/certificate-templates/delete-logo', ['path' => $path])
                ->assertStatus(422);
        }

        $this->actingAs($this->operator)
            ->deleteJson('/api/admin/certificate-templates/delete-background', ['path' => 'certificates/backgrounds/../../secret.txt'])
            ->assertStatus(422);

        $disk->assertExists('secret.txt');
        $disk->assertExists('certificates/backgrounds/bg.png');
        $disk->assertExists('certificates/1/1-1-2026-PNE.pdf');
    }

    public function test_create_form_shows_fixed_elements_enabled_by_default(): void
    {
        $html = $this->actingAs($this->operator)
            ->get(route('admin.certificate-templates.create'))
            ->assertOk()
            ->assertDontSee('data-block-type="footer"', false)
            ->assertDontSee('data-block-type="instructor_signature"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/id="show_instructor_signature"\s+name="show_instructor_signature"\s+value="1"\s+checked/', $html);
        $this->assertMatchesRegularExpression('/id="show_footer"\s+name="show_footer"\s+value="1"\s+checked/', $html);
    }

    public function test_clone_form_follows_source_template_fixed_elements(): void
    {
        $source = $this->createTemplate([
            'header' => ['type' => 'header', 'order' => 0, 'config' => ['title' => 'ZAŚWIADCZENIE']],
            'instructor_signature' => ['type' => 'instructor_signature', 'order' => 9999, 'config' => []],
        ]);

        $html = $this->actingAs($this->operator)
            ->get(route('admin.certificate-templates.create', ['clone_id' => $source->id]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/id="show_instructor_signature"\s+name="show_instructor_signature"\s+value="1"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="show_footer"\s+name="show_footer"\s+value="1"\s+checked/', $html);
    }

    public function test_edit_form_renders_gallery_endpoints(): void
    {
        $template = $this->createTemplate([
            'header' => ['type' => 'header', 'order' => 0, 'config' => ['title' => 'ZAŚWIADCZENIE']],
            'footer' => ['type' => 'footer', 'order' => 9999, 'config' => ['text' => 'Stopka']],
        ]);

        $this->actingAs($this->operator)
            ->get(route('admin.certificate-templates.edit', $template))
            ->assertOk()
            ->assertSee('/api/admin/certificate-templates/upload-logo', false)
            ->assertSee('/api/admin/certificate-templates/delete-background', false);
    }

    public function test_store_keeps_signature_and_footer_from_create_form(): void
    {
        $this->actingAs($this->operator)
            ->post(route('admin.certificate-templates.store'), [
                'name' => 'Nowy szablon',
                'is_active' => '1',
                'show_instructor_signature' => '1',
                'show_certificate_number' => '1',
                'show_footer' => '1',
                'blocks' => [
                    'block_0' => ['type' => 'header', 'order' => '0', 'config' => ['title' => 'ZAŚWIADCZENIE']],
                    'instructor_signature_new' => ['type' => 'instructor_signature', 'order' => '9999'],
                    'footer_new' => ['type' => 'footer', 'order' => '9999', 'config' => ['text' => 'Stopka testowa']],
                ],
            ])
            ->assertRedirect(route('admin.certificate-templates.index'));

        $template = CertificateTemplate::where('name', 'Nowy szablon')->firstOrFail();
        $types = collect($template->config['blocks'])->pluck('type')->all();

        $this->assertContains('instructor_signature', $types);
        $this->assertContains('footer', $types);
        $this->assertSame('Stopka testowa', $template->config['blocks']['footer_new']['config']['text']);
        $this->assertTrue($template->config['settings']['show_certificate_number']);
    }

    public function test_preview_passes_certificate_number_and_duration_to_renderer(): void
    {
        $template = $this->createTemplate([
            'course_info' => ['type' => 'course_info', 'order' => 0, 'config' => ['show_duration' => '1']],
            'instructor_signature' => ['type' => 'instructor_signature', 'order' => 9999, 'config' => []],
        ]);

        $captured = null;
        $this->mock(TemplateRenderer::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('render')
                ->once()
                ->with(Mockery::on(function (array $data) use (&$captured) {
                    $captured = $data;

                    return true;
                }))
                ->andReturn('<html><body>podglad</body></html>');
        });

        $this->actingAs($this->operator)
            ->get(route('admin.certificate-templates.preview', $template))
            ->assertOk();

        $this->assertSame('1/2025/PRZYKŁAD', $captured['certificate_number']);
        $this->assertSame(420, $captured['duration_minutes']);
        $this->assertSame(now()->toDateString(), $captured['effective_completion_date']);
    }

    private function createTemplate(array $blocks): CertificateTemplate
    {
        return CertificateTemplate::create([
            'name' => 'Szablon testowy',
            'slug' => 'szablon-testowy-'.uniqid(),
            'config' => ['blocks' => $blocks, 'settings' => ['show_certificate_number' => true]],
            'is_active' => true,
            'is_default' => false,
        ]);
    }
}
