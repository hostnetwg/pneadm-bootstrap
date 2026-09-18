<?php

namespace Tests\Feature;

use App\Mail\ChangelogVersionReleasedMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReleaseChangelogNotifyTest extends TestCase
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

    public function test_notify_sends_only_to_active_admins_and_super_admins(): void
    {
        Mail::fake();

        $adminRole = $this->role('admin', 3);
        $superRole = $this->role('super_admin', 4);
        $managerRole = $this->role('manager', 2);

        User::factory()->create(['email' => 'admin@example.test', 'role_id' => $adminRole->id, 'is_active' => true]);
        User::factory()->create(['email' => 'super@example.test', 'role_id' => $superRole->id, 'is_active' => true]);
        User::factory()->create(['email' => 'manager@example.test', 'role_id' => $managerRole->id, 'is_active' => true]);
        User::factory()->create(['email' => 'inactive@example.test', 'role_id' => $adminRole->id, 'is_active' => false]);

        $this->artisan('changelog:notify-admins', ['app' => 'adm'])
            ->assertSuccessful();

        Mail::assertSent(ChangelogVersionReleasedMail::class, 2);
        Mail::assertSent(ChangelogVersionReleasedMail::class, function (ChangelogVersionReleasedMail $mail): bool {
            return $mail->hasTo('admin@example.test');
        });
        Mail::assertSent(ChangelogVersionReleasedMail::class, function (ChangelogVersionReleasedMail $mail): bool {
            return $mail->hasTo('super@example.test');
        });
        Mail::assertNotSent(ChangelogVersionReleasedMail::class, function (ChangelogVersionReleasedMail $mail): bool {
            return $mail->hasTo('manager@example.test') || $mail->hasTo('inactive@example.test');
        });
    }

    public function test_notify_dry_run_does_not_send_mail(): void
    {
        Mail::fake();

        $adminRole = $this->role('admin', 3);
        User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $this->artisan('changelog:notify-admins', ['app' => 'adm', '--dry-run' => true])
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_notify_unknown_app_fails(): void
    {
        $this->artisan('changelog:notify-admins', ['app' => 'other'])
            ->assertFailed();
    }

    private function role(string $name, int $level): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $name],
            [
                'display_name' => $name,
                'level' => $level,
                'is_system' => true,
            ]
        );
    }
}
