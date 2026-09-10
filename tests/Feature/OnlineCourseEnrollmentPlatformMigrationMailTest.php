<?php

namespace Tests\Feature;

use App\Jobs\SendOnlineCoursePlatformMigrationEmailJob;
use App\Mail\OnlineCoursePlatformMigrationMail;
use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use App\Models\OnlineCourseEnrollmentEmailLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OnlineCourseEnrollmentPlatformMigrationMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', 'UTC'));
        config([
            'mail.system.mailer' => 'array',
            'services.pnedu_frontend_url' => 'https://pnedu.pl',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_individual_send_and_bulk_unsent_skips_expired_and_already_sent(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $course = OnlineCourse::query()->create([
            'slug' => 'kurs-mail-'.uniqid(),
            'title' => 'Kurs migracja',
            'is_active' => true,
            'visible_in_dashboard' => true,
        ]);

        $anna = OnlineCourseEnrollment::query()->create([
            'online_course_id' => $course->id,
            'email' => 'anna.nowak@example.test',
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'access_source' => 'publigo_migration',
            'access_expires_at' => null,
        ]);
        OnlineCourseEnrollment::query()->create([
            'online_course_id' => $course->id,
            'email' => 'bartek.wygasly@example.test',
            'first_name' => 'Bartek',
            'last_name' => 'Wygasły',
            'access_source' => 'publigo_migration',
            'access_expires_at' => Carbon::parse('2024-01-01 12:00:00', 'UTC'),
        ]);
        $celina = OnlineCourseEnrollment::query()->create([
            'online_course_id' => $course->id,
            'email' => 'celina.zielinska@example.test',
            'first_name' => 'Celina',
            'last_name' => 'Zielińska',
            'access_source' => 'publigo_migration',
            'access_expires_at' => Carbon::parse('2027-01-01 12:00:00', 'UTC'),
        ]);

        $this->actingAs($admin);

        $this->getJson(route('online-courses.enrollments.platform-migration-email-status', $course))
            ->assertOk()
            ->assertJsonPath('active', false)
            ->assertJsonPath('state', 'none');

        $this->postJson(route('online-courses.enrollments.platform-migration-email-cancel', $course))
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $preview = $this->get(route('online-courses.enrollments.preview-platform-migration', [$course, $anna]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('to', 'anna.nowak@example.test')
            ->assertJsonPath('access_expired', false);

        $previewJson = $preview->json();
        $this->assertStringContainsString('Przeniesienie kursu na pnedu.pl', (string) ($previewJson['subject'] ?? ''));
        $this->assertStringContainsString('Dzień dobry Anna', (string) ($previewJson['body_html'] ?? ''));
        $this->assertStringContainsString('nowoczesna-edukacja.pl', (string) ($previewJson['body_html'] ?? ''));
        $this->assertStringContainsString('Przenosimy kursy online oraz dostępy uczestników', (string) ($previewJson['body_html'] ?? ''));

        $bulkPreview = $this->get(route('online-courses.enrollments.preview-platform-migration-bulk', [
            'online_course' => $course,
            'mode' => 'unsent',
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('recipient_count', 2);
        $this->assertStringContainsString('Przeniesienie kursu na pnedu.pl', (string) ($bulkPreview->json('subject') ?? ''));

        Mail::fake();

        $this->actingAs($admin)
            ->from(route('online-courses.enrollments.index', $course))
            ->post(route('online-courses.enrollments.send-platform-migration', [$course, $anna]))
            ->assertRedirect();

        Mail::assertSent(OnlineCoursePlatformMigrationMail::class, function (OnlineCoursePlatformMigrationMail $mail) {
            return $mail->participantEmail === 'anna.nowak@example.test'
                && $mail->hasTo('anna.nowak@example.test');
        });

        $this->assertDatabaseHas('online_course_enrollment_email_logs', [
            'online_course_enrollment_id' => $anna->id,
            'type' => OnlineCourseEnrollmentEmailLog::TYPE_PLATFORM_MIGRATION,
            'status' => OnlineCourseEnrollmentEmailLog::STATUS_SENT,
        ]);

        Bus::fake();

        $this->actingAs($admin)
            ->from(route('online-courses.enrollments.index', $course))
            ->post(route('online-courses.enrollments.send-platform-migration-bulk', $course), [
                'mode' => 'unsent',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Bus::assertBatched(function ($batch) use ($celina) {
            if ($batch->jobs->count() !== 1) {
                return false;
            }
            $job = $batch->jobs->first();

            return $job instanceof SendOnlineCoursePlatformMigrationEmailJob
                && $job->enrollmentId === $celina->id;
        });
    }
}
