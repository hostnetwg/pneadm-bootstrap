<?php

namespace Tests\Feature;

use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OnlineCourseEnrollmentPubligoImportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_import_creates_enrollments_from_publigo_csv(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 11:00:00', 'Europe/Warsaw'));
        $user = User::factory()->create(['is_active' => true]);
        $course = $this->makeOnlineCourse();

        $this->actingAs($user)
            ->post(route('online-courses.enrollments.import', $course), [
                'csv_file' => $this->sampleUpload(),
            ])
            ->assertRedirect(route('online-courses.enrollments.index', $course))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('online_course_enrollments', [
            'online_course_id' => $course->id,
            'email' => 'anna.nowak@example.test',
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'phone' => '501111111',
            'legacy_publigo_user_id' => '255',
            'access_source' => 'publigo_migration',
            'access_expires_at' => null,
        ]);

        $iwona = OnlineCourseEnrollment::query()
            ->where('online_course_id', $course->id)
            ->where('email', 'iwona.solo@example.test')
            ->first();
        $this->assertNotNull($iwona);
        $this->assertSame('Iwona', $iwona->first_name);
        $this->assertNull($iwona->last_name);

        $this->assertSame(4, $course->enrollments()->count());
    }

    public function test_import_skips_existing_email_without_overwrite(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $course = $this->makeOnlineCourse();
        OnlineCourseEnrollment::query()->create([
            'online_course_id' => $course->id,
            'email' => 'anna.nowak@example.test',
            'first_name' => 'Anna',
            'last_name' => 'Istniejąca',
            'access_source' => 'manual',
        ]);

        $this->actingAs($user)
            ->post(route('online-courses.enrollments.import', $course), [
                'csv_file' => $this->sampleUpload(),
            ])
            ->assertRedirect(route('online-courses.enrollments.index', $course));

        $anna = OnlineCourseEnrollment::query()
            ->where('online_course_id', $course->id)
            ->where('email', 'anna.nowak@example.test')
            ->first();
        $this->assertSame('Istniejąca', $anna->last_name);
        $this->assertSame('manual', $anna->access_source);
        $this->assertSame(4, $course->enrollments()->count());
    }

    public function test_import_can_skip_already_expired_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 11:00:00', 'Europe/Warsaw'));
        $user = User::factory()->create(['is_active' => true]);
        $course = $this->makeOnlineCourse();

        $this->actingAs($user)
            ->post(route('online-courses.enrollments.import', $course), [
                'csv_file' => $this->sampleUpload(),
                'skip_expired' => '1',
            ])
            ->assertRedirect(route('online-courses.enrollments.index', $course));

        $this->assertSame(3, $course->enrollments()->count());
        $this->assertDatabaseMissing('online_course_enrollments', [
            'online_course_id' => $course->id,
            'email' => 'expired.user@example.test',
        ]);
        $this->assertDatabaseHas('online_course_enrollments', [
            'online_course_id' => $course->id,
            'email' => 'anna.nowak@example.test',
        ]);
    }

    private function makeOnlineCourse(): OnlineCourse
    {
        return OnlineCourse::query()->create([
            'slug' => 'kurs-import-'.uniqid(),
            'title' => 'Kurs online import test',
            'is_active' => true,
            'visible_in_dashboard' => true,
        ]);
    }

    private function sampleUpload(): UploadedFile
    {
        $contents = file_get_contents(base_path('tests/fixtures/online-courses/publigo_enrollments_sample.csv'));

        return UploadedFile::fake()->createWithContent('export.csv', $contents);
    }
}
