<?php

namespace Tests\Feature;

use App\Mail\TrainerPortalCaseSharedMail;
use App\Models\ClosedTrainingCase;
use App\Models\Instructor;
use App\Models\TrainerPortalNotification;
use App\Models\User;
use App\Services\Sms\SmsapiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TrainerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_alone_does_not_open_the_trainer_panel(): void
    {
        [$user, $instructor, $case] = $this->fixtures();

        $this->actingAs($user)
            ->put(route('closed-training-cases.negotiation.update', $case), $this->payload($instructor, true))
            ->assertRedirect();

        $case->refresh();
        $this->assertFalse($case->show_in_trainer_panel);
        $this->assertSame(0, TrainerPortalNotification::query()->count());

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('id="trainer-share-fields" class="mt-3 d-none"', false)
            ->assertDontSee('Ukryj w panelu trenera', false);
    }

    public function test_checkbox_requires_an_enabled_trainer_account(): void
    {
        [$user, $instructor, $case] = $this->fixtures(portal: false);

        $this->actingAs($user)
            ->from(route('closed-training-cases.show', $case))
            ->post(route('closed-training-cases.trainer-panel.send', $case), [
                'trainer_panel_message' => 'Proszę o termin.',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHasErrors('show_in_trainer_panel');

        $case->refresh();
        $this->assertNull($case->instructor_id);
        $this->assertFalse($case->show_in_trainer_panel);

        $case->update(['instructor_id' => $instructor->id]);

        $this->actingAs($user)
            ->from(route('closed-training-cases.show', $case))
            ->post(route('closed-training-cases.trainer-panel.send', $case), [
                'trainer_panel_message' => 'Proszę o termin.',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHasErrors('show_in_trainer_panel');

        $this->assertFalse($case->fresh()->show_in_trainer_panel);
    }

    public function test_checkbox_notifies_the_trainer_by_bell_email_and_sms_without_school_contact(): void
    {
        Mail::fake();
        $captured = null;
        $this->mock(SmsapiClient::class, function ($mock) use (&$captured): void {
            $mock->shouldReceive('sendSms')->once()->andReturnUsing(function (string $to, string $message) use (&$captured): array {
                $captured = [$to, $message];

                return ['ok' => true, 'message' => 'ok', 'message_id' => '1', 'points' => 1.0, 'raw' => null];
            });
        });

        [$user, $instructor, $case] = $this->fixtures(sms: true);
        $case->update(['instructor_id' => $instructor->id]);

        $this->actingAs($user)
            ->post(route('closed-training-cases.trainer-panel.send', $case), [
                'trainer_panel_message' => 'Proszę o termin w grudniu.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $case->refresh();
        $this->assertTrue($case->show_in_trainer_panel);
        $this->assertSame('Proszę o termin w grudniu.', $case->trainer_panel_message);
        $this->assertNotNull($case->trainer_panel_shared_at);

        $notification = TrainerPortalNotification::query()->first();
        $this->assertNotNull($notification);
        $this->assertSame(TrainerPortalNotification::AUDIENCE_TRAINER, $notification->audience);
        $this->assertSame(99, (int) $notification->pnedu_user_id);
        $this->assertNotNull($notification->email_sent_at);
        $this->assertNotNull($notification->sms_sent_at);
        $this->assertSame('48501222333', $captured[0]);
        $this->assertStringNotContainsString('600111222', $captured[1]);
        $this->assertStringNotContainsString('szkola@example.com', $captured[1]);

        Mail::assertSent(TrainerPortalCaseSharedMail::class, function (TrainerPortalCaseSharedMail $mail) use ($instructor, $user): bool {
            $text = $mail->render();

            return $mail->hasTo($instructor->email)
                && $mail->case->trainer_panel_message === 'Proszę o termin w grudniu.'
                && str_contains($text, $user->name)
                && str_contains($text, 'NODN "Platforma Nowoczesnej Edukacji"')
                && ! str_contains($text, 'Ten list nie zawiera');
        });

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('Wyłącz w panelu trenera', false)
            ->assertSee('Proszę o termin w grudniu.', false);
    }

    public function test_hiding_the_case_keeps_the_saved_date(): void
    {
        Mail::fake();
        [$user, $instructor, $case] = $this->fixtures();
        $case->update([
            'instructor_id' => $instructor->id,
            'show_in_trainer_panel' => true,
            'start_date' => '2026-12-01 09:00:00',
            'schedule_time_known' => true,
        ]);

        $this->actingAs($user)
            ->post(route('closed-training-cases.trainer-panel.hide', $case))
            ->assertRedirect();

        $case->refresh();
        $this->assertFalse($case->show_in_trainer_panel);
        $this->assertNotNull($case->start_date);

        $case->update(['show_in_trainer_panel' => true]);

        $this->actingAs($user)
            ->put(route('closed-training-cases.negotiation.update', $case), $this->payload($instructor, false, [
                'start_date' => '2026-12-01T09:00',
                'schedule_time_known' => '1',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $case->refresh();
        $this->assertFalse($case->show_in_trainer_panel);
        $this->assertNotNull($case->start_date);
    }

    /**
     * @return array{0: User, 1: Instructor, 2: ClosedTrainingCase}
     */
    private function fixtures(bool $portal = true, bool $sms = false): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $instructor = Instructor::query()->create([
            'first_name' => 'Anna',
            'last_name' => 'Trener',
            'email' => 'anna.trener.'.uniqid().'@example.com',
            'phone' => '501222333',
            'is_active' => true,
            'portal_access_enabled' => $portal,
            'sms_notifications_enabled' => $sms,
            'pnedu_user_id' => $portal ? 99 : null,
        ]);
        $case = ClosedTrainingCase::query()->create([
            'source' => ClosedTrainingCase::SOURCE_MANUAL,
            'status' => ClosedTrainingCase::STATUS_NEW,
            'topic' => 'Rada pedagogiczna',
            'contact_name' => 'Jan Szkoła',
            'contact_email' => 'szkola@example.com',
            'contact_phone' => '600111222',
            'message' => 'Prosimy o termin.',
            'inquired_at' => now(),
        ]);

        return [$user, $instructor, $case];
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, mixed>
     */
    private function payload(Instructor $instructor, bool $share = false, array $extra = []): array
    {
        return array_merge([
            'instructor_id' => $instructor->id,
            'client_price' => '',
            'instructor_fee' => '',
            'instructor_fee_basis' => ClosedTrainingCase::FEE_BASIS_GROSS,
            'start_date' => '',
            'end_date' => '',
            'show_in_trainer_panel' => $share ? '1' : '0',
        ], $extra);
    }
}
