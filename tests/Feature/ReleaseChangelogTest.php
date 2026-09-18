<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseChangelogTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();
        config([
            'release.apps.pnedu.changelog_path' => base_path('tests/fixtures/pnedu-CHANGELOG.md'),
        ]);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_guest_is_redirected_from_changelog(): void
    {
        $this->get(route('changelog.show', 'adm'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_adm_changelog_and_menu_versions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('changelog.show', 'adm'))
            ->assertOk()
            ->assertSee('adm.pnedu.pl v 1.0')
            ->assertSee('pnedu.pl v 1.0')
            ->assertSee('Aktualna wersja 1.0')
            ->assertSee('Lista ClickMeeting');
    }

    public function test_authenticated_user_sees_pnedu_changelog(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('changelog.show', 'pnedu'))
            ->assertOk()
            ->assertSee('pnedu.pl')
            ->assertSee('Aktualna wersja 1.0')
            ->assertSee('bez wpisu na publicznej stronie');
    }

    public function test_unknown_app_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/changelog/other')
            ->assertNotFound();
    }
}
