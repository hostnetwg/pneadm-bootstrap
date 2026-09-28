<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\Participant;
use App\Models\Permission;
use App\Models\PneduUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PneduUserUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.pnedu' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('pnedu');

        Schema::connection('pnedu')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('email_unique_slot')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('email_undeliverable_at')->nullable();
            $table->string('email_undeliverable_reason')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_editor_can_update_first_and_last_name(): void
    {
        $admin = $this->adminWithUsersEdit();
        $pneduUser = $this->createPneduUser();

        $this->actingAs($admin)
            ->put(route('admin.pnedu-users.update', $pneduUser), [
                'first_name' => 'Maria',
                'last_name' => 'Nowak',
                'email' => $pneduUser->email,
            ])
            ->assertRedirect(route('admin.pnedu-users.show', $pneduUser))
            ->assertSessionHas('success');

        $pneduUser->refresh();
        $this->assertSame('Maria', $pneduUser->first_name);
        $this->assertSame('Nowak', $pneduUser->last_name);
        $this->assertNotNull($pneduUser->email_verified_at);
    }

    public function test_editor_can_update_birth_date_and_place(): void
    {
        $admin = $this->adminWithUsersEdit();
        $pneduUser = $this->createPneduUser();

        $this->actingAs($admin)
            ->put(route('admin.pnedu-users.update', $pneduUser), [
                'first_name' => $pneduUser->first_name,
                'last_name' => $pneduUser->last_name,
                'email' => $pneduUser->email,
                'birth_date' => '1980-05-12',
                'birth_place' => 'Giżycko',
            ])
            ->assertRedirect(route('admin.pnedu-users.show', $pneduUser))
            ->assertSessionHas('success');

        $pneduUser->refresh();
        $this->assertSame('1980-05-12', $pneduUser->birth_date?->format('Y-m-d'));
        $this->assertSame('Giżycko', $pneduUser->birth_place);
    }

    public function test_email_change_updates_matching_participants(): void
    {
        $admin = $this->adminWithUsersEdit();
        $pneduUser = $this->createPneduUser();
        $participant = $this->createParticipantForEmail($pneduUser->email, [
            'first_name' => 'Anna',
            'last_name' => 'Test',
        ]);

        $newEmail = 'nowy-uczestnik-'.uniqid().'@example.test';

        $this->actingAs($admin)
            ->put(route('admin.pnedu-users.update', $pneduUser), [
                'first_name' => 'Maria',
                'last_name' => 'Nowak',
                'email' => $newEmail,
                'birth_date' => '1975-01-02',
                'birth_place' => 'Kraków',
            ])
            ->assertRedirect(route('admin.pnedu-users.show', $pneduUser));

        $participant->refresh();
        $this->assertSame($newEmail, $participant->email);
        $this->assertSame('Maria', $participant->first_name);
        $this->assertSame('Nowak', $participant->last_name);
        $this->assertSame('1975-01-02', $participant->birth_date?->format('Y-m-d'));
        $this->assertSame('Kraków', $participant->birth_place);
    }

    public function test_email_change_resets_verification_and_bounce_flag(): void
    {
        $admin = $this->adminWithUsersEdit();
        $pneduUser = $this->createPneduUser();
        $pneduUser->forceFill([
            'email_undeliverable_at' => now(),
            'email_undeliverable_reason' => 'permanent_bounce',
        ])->save();

        $newEmail = 'nowy-'.uniqid().'@example.test';

        $this->actingAs($admin)
            ->put(route('admin.pnedu-users.update', $pneduUser), [
                'first_name' => $pneduUser->first_name,
                'last_name' => $pneduUser->last_name,
                'email' => $newEmail,
            ])
            ->assertRedirect(route('admin.pnedu-users.show', $pneduUser))
            ->assertSessionHas('success');

        $pneduUser->refresh();
        $this->assertSame($newEmail, $pneduUser->email);
        $this->assertNull($pneduUser->email_verified_at);
        $this->assertNull($pneduUser->email_undeliverable_at);
        $this->assertNull($pneduUser->email_undeliverable_reason);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $admin = $this->adminWithUsersEdit();
        $pneduUser = $this->createPneduUser();
        $other = $this->createPneduUser();

        $this->actingAs($admin)
            ->from(route('admin.pnedu-users.show', $pneduUser))
            ->put(route('admin.pnedu-users.update', $pneduUser), [
                'first_name' => $pneduUser->first_name,
                'last_name' => $pneduUser->last_name,
                'email' => $other->email,
            ])
            ->assertRedirect(route('admin.pnedu-users.show', $pneduUser))
            ->assertSessionHasErrors('email');

        $pneduUser->refresh();
        $this->assertNotSame($other->email, $pneduUser->email);
    }

    public function test_viewer_cannot_update_pnedu_user(): void
    {
        $viewer = $this->adminWithPermission('users.view');
        $pneduUser = $this->createPneduUser();
        $originalFirst = $pneduUser->first_name;

        $this->actingAs($viewer)
            ->put(route('admin.pnedu-users.update', $pneduUser), [
                'first_name' => 'Haker',
                'last_name' => $pneduUser->last_name,
                'email' => $pneduUser->email,
            ])
            ->assertForbidden();

        $pneduUser->refresh();
        $this->assertSame($originalFirst, $pneduUser->first_name);
    }

    private function adminWithUsersEdit(): User
    {
        return $this->adminWithPermission('users.edit');
    }

    private function adminWithPermission(string $permissionName): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['name' => $permissionName],
            [
                'display_name' => $permissionName,
                'category' => 'users',
            ]
        );

        $role = Role::query()->create([
            'name' => 'pnedu_users_'.$permissionName.'_'.uniqid(),
            'display_name' => 'Rola testowa',
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
        $email = 'update-'.uniqid().'@example.test';

        return PneduUser::query()->create([
            'first_name' => 'Anna',
            'last_name' => 'Test',
            'email' => $email,
            'email_unique_slot' => PneduUser::buildEmailUniqueSlot($email, null),
            'password' => 'StareHasloTestowe1!',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createParticipantForEmail(string $email, array $overrides = []): Participant
    {
        $instructor = Instructor::query()->create([
            'first_name' => 'Jan',
            'last_name' => 'Prowadzący',
            'email' => 'prowadzacy.'.uniqid().'@example.test',
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'title' => 'Szkolenie testowe',
            'description' => 'Opis',
            'start_date' => now()->subDays(2),
            'end_date' => now()->subDay(),
            'is_paid' => false,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
        ]);

        return Participant::query()->create(array_merge([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Anna',
            'last_name' => 'Test',
            'email' => $email,
        ], $overrides));
    }
}
