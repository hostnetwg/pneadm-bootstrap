<?php

namespace Tests\Feature;

use App\Mail\CourseAccessMail;
use App\Models\Course;
use App\Models\CourseVideo;
use App\Models\Instructor;
use App\Models\Participant;
use App\Models\PneduUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecordingEnrollmentApiTest extends TestCase
{
    use RefreshDatabase;

    private const API_TOKEN = 'test-recording-enrollment-token';

    /** @var list<string> */
    private array $pneduEmails = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.pneadm.api_token' => self::API_TOKEN,
            'services.pnedu_frontend_url' => 'http://edu.localhost:8081',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->pneduEmails !== [] && $this->pneduUsersReady()) {
            PneduUser::withTrashed()->whereIn('email', $this->pneduEmails)->forceDelete();
            if (Schema::connection('pnedu')->hasTable('password_reset_tokens')) {
                Schema::connection('pnedu')->getConnection()
                    ->table('password_reset_tokens')
                    ->whereIn('email', $this->pneduEmails)
                    ->delete();
            }
        }

        parent::tearDown();
    }

    public function test_status_is_inactive_until_the_switch_is_on(): void
    {
        $this->createCourse([
            'recording_enrollment_open' => false,
            'recording_enrollment_token' => 'rec-token-closed',
        ]);

        $this->withToken(self::API_TOKEN)
            ->getJson('/api/recording-enrollment/status/rec-token-closed')
            ->assertOk()
            ->assertJsonPath('active', false);
    }

    public function test_register_creates_participant_without_pnedu_account_and_links_to_self_registration(): void
    {
        Mail::fake();

        $course = $this->createCourse();
        CourseVideo::create([
            'course_id' => $course->id,
            'video_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'platform' => 'youtube',
            'title' => 'Nagranie',
            'order' => 1,
        ]);
        $email = 'nowy.nagranie.'.uniqid().'@example.test';
        $this->pneduEmails[] = $email;

        $response = $this->withToken(self::API_TOKEN)->postJson('/api/recording-enrollment/register', [
            'token' => 'rec-token-open',
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => $email,
            'rodo_consent' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('updated', false)
            ->assertJsonPath('has_pnedu_account', false)
            ->assertJsonPath('email_sent', true);

        $this->assertStringContainsString('/register?', (string) $response->json('next_url'));
        $this->assertStringContainsString(urlencode($email), (string) $response->json('next_url'));

        $this->assertDatabaseHas('participants', [
            'course_id' => $course->id,
            'email_normalized' => $email,
            'first_name' => 'Anna',
        ]);

        if ($this->pneduUsersReady()) {
            $this->assertNull(PneduUser::query()->where('email', $email)->first());
        }

        Mail::assertSent(CourseAccessMail::class, function (CourseAccessMail $mail) use ($email): bool {
            return $mail->hasPneduAccount === false
                && $mail->participantEmail === $email
                && str_contains($mail->registerUrl, '/register?');
        });
    }

    public function test_register_existing_account_sends_login_link_and_does_not_duplicate_participant(): void
    {
        if (! $this->pneduUsersReady()) {
            $this->markTestSkipped('Brak tabeli users pnedu z kolumnami konta uczestnika.');
        }

        Mail::fake();

        $course = $this->createCourse();
        CourseVideo::create([
            'course_id' => $course->id,
            'video_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'platform' => 'youtube',
            'title' => 'Nagranie',
            'order' => 1,
        ]);
        $email = 'istniejace.nagranie.'.uniqid().'@example.test';
        $this->pneduEmails[] = $email;

        PneduUser::query()->create([
            'first_name' => 'Ewa',
            'last_name' => 'Malinowska',
            'email' => $email,
            'email_unique_slot' => PneduUser::buildEmailUniqueSlot($email, null),
            'password' => Hash::make('haslo-testowe-123'),
            'email_verified_at' => now(),
        ]);

        $first = $this->withToken(self::API_TOKEN)->postJson('/api/recording-enrollment/register', [
            'token' => 'rec-token-open',
            'first_name' => 'Ewa',
            'last_name' => 'Malinowska',
            'email' => $email,
            'rodo_consent' => 1,
        ]);

        $first->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('updated', false)
            ->assertJsonPath('has_pnedu_account', true);
        $this->assertStringContainsString('/login?', (string) $first->json('next_url'));

        Mail::assertSent(CourseAccessMail::class, function (CourseAccessMail $mail): bool {
            return $mail->hasPneduAccount === true && $mail->courseUrl !== null;
        });

        $second = $this->withToken(self::API_TOKEN)->postJson('/api/recording-enrollment/register', [
            'token' => 'rec-token-open',
            'first_name' => 'Ewa',
            'last_name' => 'Malinowska-Nowak',
            'email' => '  '.$email.' ',
            'rodo_consent' => 1,
        ]);

        $second->assertOk()->assertJsonPath('updated', true)->assertJsonPath('has_pnedu_account', true);

        $this->assertSame(1, Participant::query()->where('course_id', $course->id)->where('email_normalized', $email)->count());
        $this->assertSame(1, PneduUser::query()->where('email', $email)->count());
    }

    public function test_register_rejects_when_the_form_deadline_has_passed(): void
    {
        $this->createCourse([
            'recording_enrollment_ends_at' => now()->subMinute(),
        ]);

        $this->withToken(self::API_TOKEN)->postJson('/api/recording-enrollment/register', [
            'token' => 'rec-token-open',
            'first_name' => 'Jan',
            'last_name' => 'Test',
            'email' => 'po-terminie@example.test',
            'rodo_consent' => 1,
        ])->assertForbidden()->assertJsonPath('success', false);

        $this->assertDatabaseMissing('participants', [
            'email_normalized' => 'po-terminie@example.test',
        ]);
    }

    public function test_register_rejects_when_recording_access_has_already_expired(): void
    {
        $this->createCourse([
            'end_date' => now()->subYear(),
            'start_date' => now()->subYear()->subHours(2),
            'post_end_access_duration_value' => 1,
            'post_end_access_duration_unit' => 'days',
            'post_end_access_rule' => Course::POST_END_RULE_DURATION,
        ]);

        $this->withToken(self::API_TOKEN)
            ->getJson('/api/recording-enrollment/status/rec-token-open')
            ->assertOk()
            ->assertJsonPath('active', false);

        $this->withToken(self::API_TOKEN)->postJson('/api/recording-enrollment/register', [
            'token' => 'rec-token-open',
            'first_name' => 'Jan',
            'last_name' => 'Test',
            'email' => 'po-dostepie@example.test',
            'rodo_consent' => 1,
        ])->assertForbidden();
    }

    private function pneduUsersReady(): bool
    {
        try {
            return Schema::connection('pnedu')->hasTable('users')
                && Schema::connection('pnedu')->hasColumn('users', 'first_name')
                && Schema::connection('pnedu')->hasColumn('users', 'email_unique_slot');
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $overrides */
    private function createCourse(array $overrides = []): Course
    {
        $instructor = Instructor::create([
            'first_name' => 'Jan',
            'last_name' => 'Prowadzący',
            'email' => 'jan.prowadzacy.'.uniqid().'@example.test',
            'is_active' => true,
        ]);

        return Course::create(array_merge([
            'title' => 'Rada pedagogiczna — nagranie',
            'description' => 'Opis',
            'start_date' => now()->subHours(3),
            'end_date' => now()->subHour(),
            'is_paid' => false,
            'type' => 'online',
            'category' => 'closed',
            'instructor_id' => $instructor->id,
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'recording_enrollment_open' => true,
            'recording_enrollment_token' => 'rec-token-open',
            'post_end_access_rule' => Course::POST_END_RULE_UNLIMITED,
            'next_participant_order' => 1,
        ], $overrides));
    }
}
