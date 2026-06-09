<?php

namespace Tests\Unit;

use App\Models\CertificateEmailLog;
use App\Models\Course;
use App\Models\CourseVideo;
use App\Models\Instructor;
use App\Models\Participant;
use App\Services\ParticipantAccessExpiryReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantAccessExpiryReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    private ParticipantAccessExpiryReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'participant_access.expiry_reminder.timezone' => 'Europe/Warsaw',
            'participant_access.expiry_reminder.days_before' => [7, 1],
        ]);

        $this->service = app(ParticipantAccessExpiryReminderService::class);
    }

    private function createPaidCourseWithVideo(): Course
    {
        $instructor = Instructor::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan.kowalski@example.test',
            'is_active' => true,
        ]);

        $course = Course::create([
            'title' => 'Płatne szkolenie testowe',
            'description' => 'Opis',
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subMonths(2)->addHours(2),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'next_participant_order' => 1,
            'certificate_download_status' => 'download_enabled',
        ]);

        CourseVideo::create([
            'course_id' => $course->id,
            'title' => 'Nagranie',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'platform' => 'youtube',
            'order' => 1,
        ]);

        return $course;
    }

    public function test_finds_participant_expiring_in_seven_days_warsaw(): void
    {
        $course = $this->createPaidCourseWithVideo();
        $referenceDay = Carbon::parse('2026-06-09', 'Europe/Warsaw')->startOfDay();
        $expiresAt = Carbon::parse('2026-06-16 18:00', 'Europe/Warsaw')->utc();

        Participant::create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Anna',
            'last_name' => 'Test',
            'email' => 'anna@example.test',
            'access_expires_at' => $expiresAt,
        ]);

        $found = $this->service->participantsDueForReminder(7, $referenceDay);

        $this->assertCount(1, $found);
        $this->assertSame('anna@example.test', $found->first()->email);
    }

    public function test_excludes_unpaid_course(): void
    {
        $course = $this->createPaidCourseWithVideo();
        $course->update(['is_paid' => false]);

        $referenceDay = Carbon::parse('2026-06-09', 'Europe/Warsaw')->startOfDay();

        Participant::create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Anna',
            'last_name' => 'Test',
            'email' => 'anna@example.test',
            'access_expires_at' => Carbon::parse('2026-06-16 12:00', 'Europe/Warsaw')->utc(),
        ]);

        $this->assertCount(0, $this->service->participantsDueForReminder(7, $referenceDay));
    }

    public function test_excludes_when_reminder_already_sent(): void
    {
        $course = $this->createPaidCourseWithVideo();
        $referenceDay = Carbon::parse('2026-06-09', 'Europe/Warsaw')->startOfDay();

        $participant = Participant::create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Anna',
            'last_name' => 'Test',
            'email' => 'anna@example.test',
            'access_expires_at' => Carbon::parse('2026-06-16 12:00', 'Europe/Warsaw')->utc(),
        ]);

        CertificateEmailLog::create([
            'course_id' => $course->id,
            'participant_id' => $participant->id,
            'type' => CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER,
            'status' => CertificateEmailLog::STATUS_SENT,
            'sent_at' => now(),
            'meta' => ['days_before' => 7],
        ]);

        $this->assertTrue($this->service->reminderWasSent($participant->id, $course->id, 7));
        $this->assertCount(0, $this->service->participantsDueForReminder(7, $referenceDay));
    }

    public function test_eligible_participants_for_course_filters_by_email_and_expiry(): void
    {
        $course = $this->createPaidCourseWithVideo();
        $expiresAt = Carbon::parse('2026-07-28 14:00', 'Europe/Warsaw')->utc();

        Participant::create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Anna',
            'last_name' => 'Eligible',
            'email' => 'anna@example.test',
            'access_expires_at' => $expiresAt,
        ]);

        Participant::create([
            'course_id' => $course->id,
            'order' => 2,
            'first_name' => 'Brak',
            'last_name' => 'Email',
            'email' => null,
            'access_expires_at' => $expiresAt,
        ]);

        Participant::create([
            'course_id' => $course->id,
            'order' => 3,
            'first_name' => 'Wygasly',
            'last_name' => 'Dostep',
            'email' => 'expired@example.test',
            'access_expires_at' => now('UTC')->subDay(),
        ]);

        $eligible = $this->service->eligibleParticipantsForCourse($course, true, false);

        $this->assertCount(1, $eligible);
        $this->assertSame('anna@example.test', $eligible->first()->email);
    }
}
