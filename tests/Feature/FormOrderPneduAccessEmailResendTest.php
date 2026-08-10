<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\PneduUser;
use App\Models\User;
use App\Notifications\PneduFormOrderProvisionedExistingUser;
use App\Notifications\PneduFormOrderProvisionedNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormOrderPneduAccessEmailResendTest extends TestCase
{
    use RefreshDatabase;

    private function createProvisionedOrder(bool $userExistedBefore): FormOrder
    {
        $courseId = (int) DB::table('courses')->insertGetId([
            'title' => 'Kurs e-mail resend',
            'description' => 'Test',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $email = $userExistedBefore ? 'existing.resend@example.test' : 'new.resend@example.test';

        $order = FormOrder::query()->create([
            'product_id' => $courseId,
            'product_name' => 'Kurs e-mail resend',
            'status_completed' => 0,
            'orderer_email' => $email,
            'pnedu_provisioned_at' => now(),
            'pnedu_user_existed_before' => $userExistedBefore,
        ]);

        FormOrderParticipant::query()->create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Anna',
            'participant_lastname' => 'Nowak',
            'participant_email' => $email,
            'is_primary' => true,
        ]);

        PneduUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Anna',
                'last_name' => 'Nowak',
                'email_unique_slot' => PneduUser::buildEmailUniqueSlot($email, null),
                'password' => Hash::make('secret-password-123'),
                'email_verified_at' => now(),
            ]
        );

        return $order->fresh();
    }

    public function test_preview_and_resend_existing_user_access_email(): void
    {
        if (! Schema::connection('pnedu')->hasTable('users')) {
            $this->markTestSkipped('Brak tabeli users w połączeniu pnedu.');
        }

        Notification::fake();

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);
        $order = $this->createProvisionedOrder(true);

        $preview = $this->actingAs($admin)->getJson(
            route('form-orders.pnedu.access-email-preview', $order->id)
        );

        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('variant', 'existing_user')
            ->assertJsonPath('to', 'existing.resend@example.test');
        $this->assertNotEmpty($preview->json('subject'));
        $this->assertNotEmpty($preview->json('body'));

        $send = $this->actingAs($admin)->postJson(
            route('form-orders.pnedu.resend-access-email', $order->id)
        );

        $send->assertOk()->assertJsonPath('success', true);

        Notification::assertSentTo(
            PneduUser::query()->where('email', 'existing.resend@example.test')->first(),
            PneduFormOrderProvisionedExistingUser::class
        );
    }

    public function test_resend_new_user_sends_password_setup_email(): void
    {
        if (! Schema::connection('pnedu')->hasTable('users')) {
            $this->markTestSkipped('Brak tabeli users w połączeniu pnedu.');
        }

        Notification::fake();

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);
        $order = $this->createProvisionedOrder(false);

        $preview = $this->actingAs($admin)->getJson(
            route('form-orders.pnedu.access-email-preview', $order->id)
        );
        $preview->assertOk()->assertJsonPath('variant', 'new_user');
        $this->assertStringContainsString('PODGLAD-LINK-WYGENEROWANY-PRZY-WYSYLCE', (string) $preview->json('body'));

        $send = $this->actingAs($admin)->postJson(
            route('form-orders.pnedu.resend-access-email', $order->id)
        );
        $send->assertOk()->assertJsonPath('success', true);

        Notification::assertSentTo(
            PneduUser::query()->where('email', 'new.resend@example.test')->first(),
            PneduFormOrderProvisionedNewUser::class
        );
    }

    public function test_preview_requires_provisioned_order(): void
    {
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::query()->create([
            'product_name' => 'Bez PNEDU',
            'orderer_email' => 'x@example.test',
            'pnedu_provisioned_at' => null,
        ]);

        $this->actingAs($admin)
            ->getJson(route('form-orders.pnedu.access-email-preview', $order->id))
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }
}
