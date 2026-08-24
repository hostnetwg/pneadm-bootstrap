<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PneduUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PneduUserSetPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (! Schema::connection('pnedu')->hasTable('users')) {
                $this->markTestSkipped('Brak tabeli users w bazie pnedu.');
            }
        } catch (\Throwable) {
            $this->markTestSkipped('Brak połączenia z testową bazą pnedu.');
        }
    }

    public function test_set_password_redirects_to_user_show_even_if_previous_url_is_filter_count(): void
    {
        $admin = $this->adminWithUsersEdit();
        $pneduUser = $this->createPneduUser();

        $this->actingAs($admin)
            ->from(route('form-orders.navigation-filter-count'))
            ->post(route('admin.pnedu-users.set-password', $pneduUser), [
                'password' => 'NoweHasloTestowe1!',
                'password_confirmation' => 'NoweHasloTestowe1!',
            ])
            ->assertRedirect(route('admin.pnedu-users.show', $pneduUser))
            ->assertSessionHas('success');
    }

    private function adminWithUsersEdit(): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['name' => 'users.edit'],
            [
                'display_name' => 'Edycja użytkowników',
                'category' => 'users',
            ]
        );

        $role = Role::query()->create([
            'name' => 'pnedu_users_editor_'.uniqid(),
            'display_name' => 'Edytor użytkowników pnedu',
            'level' => 3,
        ]);
        $role->permissions()->attach($permission->id);

        return User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
            'role_id' => $role->id,
        ]);
    }

    private function createPneduUser(): PneduUser
    {
        $email = 'set-password-'.uniqid().'@example.test';

        return PneduUser::query()->create([
            'first_name' => 'Anna',
            'last_name' => 'Test',
            'email' => $email,
            'email_unique_slot' => PneduUser::buildEmailUniqueSlot($email, null),
            'password' => 'StareHasloTestowe1!',
        ]);
    }
}
