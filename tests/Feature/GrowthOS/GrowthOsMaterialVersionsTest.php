<?php

namespace Tests\Feature\GrowthOS;

use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthArtifactVersion;
use App\Models\Role;
use App\Models\User;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GrowthOsMaterialVersionsTest extends TestCase
{
    use RefreshDatabase;

    private const MATERIAL = 'youtube-description';

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();
        config()->set('growth_os.enabled', true);
        config()->set('growth_ai.enabled', false);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_manual_save_creates_version(): void
    {
        $user = $this->projectOwner();

        $this->saveMaterial($user, 'REVIEW', 'Pierwszy opis');

        $versions = $this->versions();
        $this->assertCount(1, $versions);
        $this->assertSame(1, $versions[0]->version);
        $this->assertSame(GrowthArtifactVersion::SOURCE_MANUAL, $versions[0]->source);
        $this->assertEqualsCanonicalizing(['status' => 'REVIEW', 'draft' => 'Pierwszy opis'], $versions[0]->payload);
        $this->assertSame($user->id, $versions[0]->created_by_user_id);
    }

    public function test_status_only_change_creates_no_version(): void
    {
        $user = $this->projectOwner();
        $this->saveMaterial($user, 'REVIEW', 'Opis');

        $this->saveMaterial($user, 'APPROVED', 'Opis');

        $this->assertCount(1, $this->versions());
        $this->assertSame(2, $this->artifact()->version);
    }

    public function test_material_saved_before_history_keeps_baseline(): void
    {
        $user = $this->projectOwner();
        $this->saveMaterial($user, 'APPROVED', 'Opis sprzed historii');
        GrowthArtifactVersion::query()->delete();

        $this->saveMaterial($user, 'DRAFT', 'Nowy opis');

        $versions = $this->versions();
        $this->assertSame([2, 1], $versions->pluck('version')->all());
        $this->assertSame(GrowthArtifactVersion::SOURCE_BASELINE, $versions[1]->source);
        $this->assertSame('Opis sprzed historii', $versions[1]->payload['draft']);
        $this->assertNull($versions[1]->created_by_user_id);
        $this->assertSame('Nowy opis', $versions[0]->payload['draft']);
    }

    public function test_ai_apply_creates_ai_version(): void
    {
        $user = $this->projectOwner();
        $this->approve($user, 'direction');
        $this->approve($user, 'concept');
        $this->saveMaterial($user, 'REVIEW', 'Ręczny opis');

        $this->actingAs($user)->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]));
        $this->actingAs($user)->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSessionHas('success');

        $versions = $this->versions();
        $this->assertCount(2, $versions);
        $this->assertSame(GrowthArtifactVersion::SOURCE_AI_APPLY, $versions[0]->source);
        $this->assertSame('DRAFT', $versions[0]->payload['status']);
        $this->assertSame('Ręczny opis', $versions[1]->payload['draft']);
    }

    public function test_only_latest_twenty_versions_are_kept(): void
    {
        $user = $this->projectOwner();

        for ($i = 1; $i <= 22; $i++) {
            $this->saveMaterial($user, 'DRAFT', 'Opis '.$i);
        }

        $versions = $this->versions();
        $this->assertCount(GrowthArtifactVersion::KEEP_LATEST, $versions);
        $this->assertSame(22, $versions->first()->version);
        $this->assertSame(3, $versions->last()->version);
    }

    public function test_versions_are_isolated_per_material(): void
    {
        $user = $this->projectOwner();
        $this->saveMaterial($user, 'DRAFT', 'Opis YouTube');
        $this->saveMaterial($user, 'DRAFT', 'Post', 'facebook-post');

        $this->assertCount(1, $this->versions());
        $this->assertCount(1, $this->versions('facebook-post'));
        $this->assertSame('Post', $this->versions('facebook-post')[0]->payload['draft']);
    }

    public function test_restore_writes_old_text_as_new_draft_version(): void
    {
        $user = $this->projectOwner();
        $this->saveMaterial($user, 'APPROVED', 'Dobry opis');
        $this->saveMaterial($user, 'APPROVED', 'Gorszy opis');

        $this->restore($user, 1)
            ->assertSessionHas('success', 'Przywrócono wersję 1 jako nową wersję. Materiał ma status Draft — sprawdź go przed dalszą pracą.');

        $material = DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, self::MATERIAL);
        $this->assertSame('Dobry opis', $material['draft']);
        $this->assertSame('DRAFT', $material['status']);
        $this->assertSame('Dobry opis', $this->artifact()->payload['draft']);
        $this->assertSame(GrowthArtifact::STATUS_DRAFT, $this->artifact()->status);

        $versions = $this->versions();
        $this->assertSame([3, 2, 1], $versions->pluck('version')->all());
        $this->assertSame(GrowthArtifactVersion::SOURCE_RESTORE, $versions[0]->source);
        $this->assertSame(1, $versions[0]->restored_from_version);
        $this->assertSame('Gorszy opis', $versions[1]->payload['draft']);
    }

    public function test_restore_of_current_text_changes_nothing(): void
    {
        $user = $this->projectOwner();
        $this->saveMaterial($user, 'APPROVED', 'Opis');

        $this->restore($user, 1)
            ->assertSessionHas('success', 'Ta wersja jest taka sama jak obecny szkic. Nic nie zmieniono.');

        $this->assertCount(1, $this->versions());
        $this->assertSame('APPROVED', DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, self::MATERIAL)['status']);
    }

    public function test_unknown_version_or_material_returns_404(): void
    {
        $user = $this->projectOwner();
        $this->saveMaterial($user, 'DRAFT', 'Opis');

        $this->actingAs($user)
            ->post(route('growth.projects.materials.versions.restore', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL, 99]))
            ->assertNotFound();
        $this->actingAs($user)
            ->post(route('growth.projects.materials.versions.restore', [DemoTikWebinarProject::PROJECT_ID, 'facebook-post', 1]))
            ->assertNotFound();
        $this->actingAs($user)
            ->post(route('growth.projects.materials.versions.restore', [DemoTikWebinarProject::PROJECT_ID, 'unknown', 1]))
            ->assertNotFound();
    }

    public function test_restore_makes_pending_ai_proposal_stale(): void
    {
        $user = $this->projectOwner();
        $this->approve($user, 'direction');
        $this->approve($user, 'concept');
        $this->saveMaterial($user, 'DRAFT', 'Opis pierwszy');
        $this->saveMaterial($user, 'DRAFT', 'Opis drugi');
        $this->actingAs($user)->post(route('growth.projects.materials.ai', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]));

        $this->restore($user, 1);

        $this->actingAs($user)
            ->post(route('growth.projects.materials.ai.apply', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSessionHas('error');
        $this->assertSame('Opis pierwszy', DemoTikWebinarProject::material(DemoTikWebinarProject::PROJECT_ID, self::MATERIAL)['draft']);
    }

    public function test_page_lists_versions_with_bootstrap_modal_and_survives_new_session(): void
    {
        $user = $this->projectOwner();
        $this->saveMaterial($user, 'DRAFT', 'Opis pierwszy');
        $this->saveMaterial($user, 'DRAFT', 'Opis drugi');
        session()->forget(DemoTikWebinarProject::SESSION_PROJECT);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertOk()
            ->assertSee('Historia wersji')
            ->assertSee('Aktualna')
            ->assertSee('Zapis ręczny')
            ->assertSee('data-bs-target="#material-version-1"', false)
            ->assertSee('Przywróć tę wersję')
            ->assertDontSee('confirm(', false);
    }

    public function test_repeated_text_is_marked_with_its_first_version(): void
    {
        $user = $this->projectOwner();
        $this->saveMaterial($user, 'DRAFT', 'Tekst A');
        $this->saveMaterial($user, 'DRAFT', 'Tekst B');
        $this->restore($user, 1);
        $this->restore($user, 2);

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertSee('ten sam tekst co v1')
            ->assertSee('ten sam tekst co v2')
            ->assertDontSee('ten sam tekst co v3');
    }

    public function test_page_without_saved_material_shows_no_history(): void
    {
        $user = $this->projectOwner();

        $this->actingAs($user)
            ->get(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]))
            ->assertOk()
            ->assertDontSee('Historia wersji');
    }

    private function restore(User $user, int $version): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->post(route('growth.projects.materials.versions.restore', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL, $version]))
            ->assertRedirect(route('growth.projects.materials.show', [DemoTikWebinarProject::PROJECT_ID, self::MATERIAL]));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, GrowthArtifactVersion>
     */
    private function versions(string $key = self::MATERIAL): \Illuminate\Database\Eloquent\Collection
    {
        return GrowthArtifactVersion::query()
            ->whereHas('artifact', fn ($query) => $query->where('key', $key))
            ->orderByDesc('version')
            ->get();
    }

    private function artifact(): GrowthArtifact
    {
        $artifact = GrowthArtifact::query()->where('key', self::MATERIAL)->first();
        $this->assertNotNull($artifact);

        return $artifact;
    }

    private function saveMaterial(User $user, string $status, string $draft, string $key = self::MATERIAL): void
    {
        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, $key]), [
                'status' => $status,
                'draft' => $draft,
            ])
            ->assertRedirect();
    }

    private function approve(User $user, string $step): void
    {
        $this->actingAs($user)
            ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, $step]))
            ->assertRedirect();
    }

    private function projectOwner(): User
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
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user)->post(route('growth.projects.store'), [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => 'Canva AI w pracy nauczyciela',
        ]);

        return $user;
    }
}
