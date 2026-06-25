<?php

namespace Tests\Feature;

use App\Models\AnalyticsSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnalyticsSettingsPanelTest extends TestCase
{
    private array $createdUserIds = [];

    private array $createdRoleIds = [];

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputBufferLevel = ob_get_level();

        if (! Schema::hasTable('users') || ! Schema::hasTable('roles') || ! Schema::hasTable('analytics_settings')) {
            $this->markTestSkipped('Brak tabel users/roles/analytics_settings w testowej bazie adm.');
        }

        config()->set('analytics.enabled', true);
        config()->set('analytics.default_mode', 'standard');
        config()->set('analytics.sample_rate', 100);

        $this->resetSettings();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        $this->resetSettings();

        if ($this->createdUserIds !== []) {
            User::query()->withTrashed()->whereIn('id', $this->createdUserIds)->forceDelete();
        }

        if ($this->createdRoleIds !== []) {
            Role::query()->whereIn('id', $this->createdRoleIds)->delete();
        }

        parent::tearDown();
    }

    public function test_guest_cannot_access_settings_panel(): void
    {
        $this->get(route('analytics.settings.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_settings_panel(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)
            ->get(route('analytics.settings.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_settings_panel_with_config_values(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.settings.index'))
            ->assertOk()
            ->assertSee('Analityka — Ustawienia')
            ->assertSee('ANALYTICS_ENABLED')
            ->assertSee('ANALYTICS_DEFAULT_MODE')
            ->assertSee('ANALYTICS_SAMPLE_RATE');
    }

    public function test_panel_shows_runtime_override_when_present(): void
    {
        $admin = $this->userWithRole('admin');
        $this->setOverride(true, 'light');

        $this->actingAs($admin)
            ->get(route('analytics.settings.index'))
            ->assertOk()
            ->assertSee('light');
    }

    public function test_admin_can_save_enabled_override_enabled(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('analytics.settings.update'), [
                'enabled_override' => 'enabled',
                'default_mode_override' => 'use_config',
            ])
            ->assertRedirect(route('analytics.settings.index'));

        $this->assertSame(1, (int) DB::table('analytics_settings')->where('id', 1)->value('enabled_override'));
    }

    public function test_admin_can_save_enabled_override_disabled_with_confirmation(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('analytics.settings.update'), [
                'enabled_override' => 'disabled',
                'default_mode_override' => 'use_config',
                'confirm_impact' => '1',
            ])
            ->assertRedirect(route('analytics.settings.index'));

        $this->assertSame(0, (int) DB::table('analytics_settings')->where('id', 1)->value('enabled_override'));
    }

    public function test_disabling_without_confirmation_is_rejected(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('analytics.settings.update'), [
                'enabled_override' => 'disabled',
                'default_mode_override' => 'use_config',
            ])
            ->assertSessionHasErrors('confirm_impact');
    }

    public function test_admin_can_save_enabled_override_use_config(): void
    {
        $admin = $this->userWithRole('admin');
        $this->setOverride(true, null);

        $this->actingAs($admin)
            ->post(route('analytics.settings.update'), [
                'enabled_override' => 'use_config',
                'default_mode_override' => 'use_config',
            ])
            ->assertRedirect(route('analytics.settings.index'));

        $this->assertNull(DB::table('analytics_settings')->where('id', 1)->value('enabled_override'));
    }

    public function test_admin_can_save_default_mode_standard(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('analytics.settings.update'), [
                'enabled_override' => 'use_config',
                'default_mode_override' => 'standard',
            ])
            ->assertRedirect(route('analytics.settings.index'));

        $this->assertSame('standard', DB::table('analytics_settings')->where('id', 1)->value('default_mode_override'));
    }

    public function test_admin_can_save_default_mode_off_with_confirmation(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('analytics.settings.update'), [
                'enabled_override' => 'use_config',
                'default_mode_override' => 'off',
                'confirm_impact' => '1',
            ])
            ->assertRedirect(route('analytics.settings.index'));

        $this->assertSame('off', DB::table('analytics_settings')->where('id', 1)->value('default_mode_override'));
    }

    public function test_admin_can_save_default_mode_use_config(): void
    {
        $admin = $this->userWithRole('admin');
        $this->setOverride(null, 'light');

        $this->actingAs($admin)
            ->post(route('analytics.settings.update'), [
                'enabled_override' => 'use_config',
                'default_mode_override' => 'use_config',
            ])
            ->assertRedirect(route('analytics.settings.index'));

        $this->assertNull(DB::table('analytics_settings')->where('id', 1)->value('default_mode_override'));
    }

    public function test_validation_rejects_unknown_mode(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('analytics.settings.update'), [
                'enabled_override' => 'use_config',
                'default_mode_override' => 'banana',
            ])
            ->assertSessionHasErrors('default_mode_override');
    }

    public function test_cache_is_cleared_after_save(): void
    {
        $admin = $this->userWithRole('admin');

        AnalyticsSetting::getSettings();
        $this->assertTrue(Cache::has(AnalyticsSetting::SETTINGS_CACHE_KEY));

        $this->actingAs($admin)
            ->post(route('analytics.settings.update'), [
                'enabled_override' => 'use_config',
                'default_mode_override' => 'standard',
            ])
            ->assertRedirect(route('analytics.settings.index'));

        $this->assertFalse(Cache::has(AnalyticsSetting::SETTINGS_CACHE_KEY));
    }

    public function test_menu_contains_analytics_settings_and_renamed_ga_cookie(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('analytics.settings.index'))
            ->assertOk()
            ->assertSee('GA i lejek (cookie)')
            ->assertSee(route('analytics.settings.index'), false);
    }

    public function test_form_does_not_expose_secrets(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('analytics.settings.index'));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('APP_KEY', $content);
        $this->assertStringNotContainsString('DB_PASSWORD', $content);
        $this->assertStringNotContainsString(config('app.key'), $content);
    }

    private function resetSettings(): void
    {
        DB::table('analytics_settings')->updateOrInsert(
            ['id' => AnalyticsSetting::SINGLETON_ID],
            ['enabled_override' => null, 'default_mode_override' => null, 'updated_by' => null, 'updated_at' => now()],
        );
        AnalyticsSetting::forgetSettingsCache();
    }

    private function setOverride(?bool $enabled, ?string $mode): void
    {
        DB::table('analytics_settings')->updateOrInsert(
            ['id' => AnalyticsSetting::SINGLETON_ID],
            ['enabled_override' => $enabled, 'default_mode_override' => $mode, 'updated_at' => now()],
        );
        AnalyticsSetting::forgetSettingsCache();
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()->where('name', $roleName)->first();
        if (! $role) {
            $role = Role::query()->create([
                'name' => $roleName,
                'display_name' => ucfirst(str_replace('_', ' ', $roleName)),
                'is_system' => true,
                'level' => $roleName === 'admin' ? 3 : 2,
            ]);
            $this->createdRoleIds[] = $role->id;
        }

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $this->createdUserIds[] = $user->id;

        return $user;
    }
}
