<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use App\Models\PneduUser;
use Tests\TestCase;

class PneduUserProvisionEmailTest extends TestCase
{
    public function test_needs_password_setup_when_never_logged_in_and_created_at_provision(): void
    {
        $now = now();
        $order = new FormOrder([
            'pnedu_provisioned_at' => $now,
            'pnedu_user_existed_before' => false,
        ]);

        $user = new PneduUser;
        $user->forceFill([
            'last_login_at' => null,
            'login_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->assertTrue($user->needsProvisionPasswordSetupEmail($order));
    }

    public function test_does_not_need_password_setup_after_login(): void
    {
        $order = new FormOrder([
            'pnedu_provisioned_at' => now(),
            'pnedu_user_existed_before' => false,
        ]);

        $user = new PneduUser;
        $user->forceFill([
            'last_login_at' => now(),
            'login_count' => 1,
        ]);

        $this->assertFalse($user->needsProvisionPasswordSetupEmail($order));
    }

    public function test_does_not_need_password_setup_when_account_predates_provision(): void
    {
        $provisionedAt = now();

        $order = new FormOrder([
            'pnedu_provisioned_at' => $provisionedAt,
            'pnedu_user_existed_before' => false,
        ]);

        $user = new PneduUser;
        $user->forceFill([
            'last_login_at' => null,
            'login_count' => 0,
            'created_at' => $provisionedAt->copy()->subDay(),
            'updated_at' => $provisionedAt->copy()->subDay(),
        ]);

        $this->assertFalse($user->needsProvisionPasswordSetupEmail($order));
    }

    public function test_does_not_need_password_setup_when_password_changed_after_creation(): void
    {
        $created = now()->subHour();
        $order = new FormOrder([
            'pnedu_provisioned_at' => now(),
            'pnedu_user_existed_before' => false,
        ]);

        $user = new PneduUser;
        $user->forceFill([
            'last_login_at' => null,
            'login_count' => 0,
            'created_at' => $created,
            'updated_at' => $created->copy()->addMinutes(5),
        ]);

        $this->assertFalse($user->needsProvisionPasswordSetupEmail($order));
    }
}
