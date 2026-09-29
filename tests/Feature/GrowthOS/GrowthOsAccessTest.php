<?php

namespace Tests\Feature\GrowthOS;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthOsAccessTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login_when_feature_flag_is_enabled(): void
    {
        config()->set('growth_os.enabled', true);

        $this->get(route('growth.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_growth_os_is_unavailable_and_hidden_when_feature_flag_is_disabled(): void
    {
        config()->set('growth_os.enabled', false);
        $user = $this->userWithRole('super_admin', 100);

        $this->actingAs($user)
            ->get(route('growth.dashboard'))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('PNE Rozwój');
    }

    public function test_growth_os_fails_closed_for_invalid_config_value(): void
    {
        config()->set('growth_os.enabled', 'invalid');
        $user = $this->userWithRole('super_admin', 100);

        $this->actingAs($user)
            ->get(route('growth.dashboard'))
            ->assertNotFound();
    }

    public function test_user_without_temporary_super_admin_access_cannot_open_or_see_growth_os(): void
    {
        config()->set('growth_os.enabled', true);
        $user = $this->userWithRole('admin', 50);

        $this->actingAs($user)
            ->get(route('growth.dashboard'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('PNE Rozwój');
    }

    public function test_authorized_user_can_open_demo_dashboard_and_see_menu_item(): void
    {
        config()->set('growth_os.enabled', true);
        $user = $this->userWithRole('super_admin', 100);

        $this->actingAs($user)
            ->get(route('growth.dashboard'))
            ->assertOk()
            ->assertSee('PNE Rozwój')
            ->assertSee('Dzisiaj')
            ->assertSee('Projekty')
            ->assertSee('Pomysły')
            ->assertSee('Inbox')
            ->assertSee('Etap 0.3')
            ->assertSee('Zaplanuj webinar TIK')
            ->assertSee(route('growth.dashboard'), false);
    }

    private function userWithRole(string $name, int $level): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $name],
            [
                'display_name' => $name,
                'description' => 'Rola testowa Growth OS',
                'is_system' => true,
                'level' => $level,
            ]
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
