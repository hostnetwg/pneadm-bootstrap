<?php

namespace Tests\Feature;

use App\Mail\ParticipantCustomNoticeMail;
use App\Models\CertificateEmailLog;
use App\Models\Course;
use App\Models\CourseOnlineDetails;
use App\Models\Participant;
use App\Models\ParticipantLiveAccess;
use App\Models\User;
use App\Services\ClickMeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ParticipantCustomNoticeMailTest extends TestCase
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

    public function test_guest_cannot_send_custom_notice(): void
    {
        $course = $this->createCourse();

        $this->post(route('participants.send-custom-notice', $course), [
            'subject' => 'Temat',
            'body' => 'Treść',
        ])->assertRedirect(route('login'));
    }

    public function test_participants_page_shows_editable_notice_with_clickmeeting_draft(): void
    {
        $user = User::factory()->create();
        $course = $this->createCourse();

        $response = $this->actingAs($user)->get(route('participants.index', $course));

        $response->assertOk();
        $response->assertSee('Wyślij wiadomość do wszystkich', false);
        $response->assertSee('id="customNoticeModal"', false);
        $response->assertSee('Awaria platformy ClickMeeting — Szkolenie testowe', false);
        $response->assertSee('platforma ClickMeeting ma obecnie awarię', false);
        $response->assertSee('Podgląd przed wysłaniem', false);
    }

    public function test_custom_notice_sends_to_each_valid_address_and_keeps_subject_and_body(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $course = $this->createCourse();

        $this->addParticipant($course, 'Anna', 'anna@example.test');
        $this->addParticipant($course, 'Bartek', 'bartek@example.test');
        $this->addParticipant($course, 'Bez', '');
        $this->addParticipant($course, 'Zly', 'nie-email');

        $subject = 'Informacja o awarii';
        $body = "Dzień dobry,\n\nplatforma nie działa: https://clickmeeting.com\n\nPozdrawiamy";

        $this->actingAs($user)
            ->post(route('participants.send-custom-notice', $course), [
                'subject' => $subject,
                'body' => $body,
            ])
            ->assertRedirect(route('participants.index', $course))
            ->assertSessionHas('success', function (string $message) {
                return str_contains($message, 'Wysłano wiadomość do 2 adresów')
                    && str_contains($message, 'Pominięto 1 nieprawidłowych adresów');
            });

        Mail::assertSent(ParticipantCustomNoticeMail::class, 2);
        Mail::assertSent(ParticipantCustomNoticeMail::class, function (ParticipantCustomNoticeMail $mail) use ($subject, $body) {
            return $mail->hasTo('anna@example.test')
                && $mail->subjectLine === $subject
                && $mail->plainBody === $body;
        });
        Mail::assertSent(ParticipantCustomNoticeMail::class, function (ParticipantCustomNoticeMail $mail) {
            return $mail->hasTo('bartek@example.test');
        });

        $this->assertSame(2, CertificateEmailLog::query()
            ->where('course_id', $course->id)
            ->where('type', CertificateEmailLog::TYPE_CUSTOM_NOTICE)
            ->where('status', CertificateEmailLog::STATUS_SENT)
            ->count());
    }

    public function test_rendered_notice_keeps_line_breaks_and_escapes_html(): void
    {
        $course = $this->createCourse();
        $mail = new ParticipantCustomNoticeMail(
            $course,
            "Linia 1\nhttps://pnedu.pl/info\n<script>alert(1)</script>",
            'Temat'
        );

        $html = $mail->render();

        $this->assertStringContainsString('href="https://pnedu.pl/info"', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_blank_subject_does_not_send(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $course = $this->createCourse();
        $this->addParticipant($course, 'Anna', 'anna@example.test');

        $this->actingAs($user)
            ->from(route('participants.index', $course))
            ->post(route('participants.send-custom-notice', $course), [
                'subject' => '   ',
                'body' => 'Treść',
            ])
            ->assertRedirect(route('participants.index', $course))
            ->assertSessionHasErrors('subject');

        Mail::assertNothingSent();
    }

    public function test_included_live_link_is_each_participants_clickmeeting_token_url(): void
    {
        Mail::fake();
        config(['services.clickmeeting.token' => '']);
        $user = User::factory()->create();
        $course = $this->createCourse();
        CourseOnlineDetails::query()->create([
            'course_id' => $course->id,
            'platform' => 'clickmeeting',
            'clickmeeting_event_id' => '10229999',
            'meeting_link' => 'https://pnedu.clickmeeting.com/szkolenie-test',
        ]);

        $anna = $this->addParticipant($course, 'Anna', 'anna@example.test');
        $bartek = $this->addParticipant($course, 'Bartek', 'bartek@example.test');
        $bezTokenu = $this->addParticipant($course, 'Cela', 'cela@example.test');
        $this->addLiveToken($anna, 'TOK-ANNA');
        $this->addLiveToken($bartek, 'TOK-BARTEK');

        $this->actingAs($user)
            ->post(route('participants.send-custom-notice', $course), [
                'subject' => 'Link do spotkania',
                'body' => "Dzień dobry,\n\nwejście: {link}",
                'include_live_link' => '1',
            ])
            ->assertRedirect(route('participants.index', $course))
            ->assertSessionHas('success');

        Mail::assertSent(ParticipantCustomNoticeMail::class, 2);
        Mail::assertSent(ParticipantCustomNoticeMail::class, function (ParticipantCustomNoticeMail $mail) {
            return $mail->hasTo('anna@example.test')
                && str_contains($mail->plainBody, 'https://pnedu.clickmeeting.com/szkolenie-test/TOK-ANNA')
                && ! str_contains($mail->plainBody, 'TOK-BARTEK')
                && ! str_contains($mail->plainBody, '{link}');
        });
        Mail::assertSent(ParticipantCustomNoticeMail::class, function (ParticipantCustomNoticeMail $mail) {
            return $mail->hasTo('bartek@example.test')
                && str_contains($mail->plainBody, 'https://pnedu.clickmeeting.com/szkolenie-test/TOK-BARTEK');
        });
        Mail::assertNotSent(ParticipantCustomNoticeMail::class, function (ParticipantCustomNoticeMail $mail) use ($bezTokenu) {
            return $mail->hasTo($bezTokenu->email);
        });
    }

    private function createCourse(): Course
    {
        $start = now()->addDay();

        return Course::query()->create([
            'title' => 'Szkolenie testowe',
            'description' => 'Test',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(2),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/PNE',
        ]);
    }

    private function addParticipant(Course $course, string $firstName, string $email): Participant
    {
        return Participant::query()->create([
            'course_id' => $course->id,
            'order' => (int) Participant::query()->where('course_id', $course->id)->count() + 1,
            'first_name' => $firstName,
            'last_name' => 'Test',
            'email' => $email,
        ]);
    }

    private function addLiveToken(Participant $participant, string $token): void
    {
        ParticipantLiveAccess::query()->create([
            'participant_id' => $participant->id,
            'course_id' => $participant->course_id,
            'platform' => 'clickmeeting',
            'access_type' => ClickMeetingService::ACCESS_TYPE_TOKEN,
            'token' => $token,
            'room_url' => 'https://pnedu.clickmeeting.com/szkolenie-test',
            'status' => 'success',
            'synced_at' => now(),
        ]);
    }
}
