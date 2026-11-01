<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Instructor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificatePdfRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['certificate_pdf_retention.days' => 30]);
    }

    private function makeCourse(array $overrides = []): Course
    {
        $instructor = Instructor::create([
            'first_name' => 'Jan',
            'last_name' => 'Prowadzący',
            'email' => 'jan.retencja@example.test',
            'is_active' => true,
        ]);

        return Course::create(array_merge([
            'title' => 'Szkolenie testowe',
            'description' => 'Opis',
            'start_date' => now()->subDays(100),
            'end_date' => now()->subDays(100)->addHour(),
            'is_paid' => false,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'next_participant_order' => 1,
        ], $overrides));
    }

    private function makeCertificate(string $number, string $path, string $generatedAt, array $overrides = [], bool $withFile = true): Certificate
    {
        if ($withFile) {
            Storage::disk('public')->put($path, '%PDF-1.4 test');
        }

        $certificate = new Certificate(array_merge([
            'certificate_number' => $number,
            'file_path' => 'storage/'.$path,
            'generated_at' => $generatedAt,
        ], $overrides));
        $certificate->save();

        return $certificate->fresh();
    }

    public function test_deletes_old_pdf_and_nulls_file_path_but_keeps_recent_and_record(): void
    {
        $old = $this->makeCertificate('1/1/2026/PNE', 'certificates/1/1-1-2026-PNE.pdf', now()->subDays(45)->toDateTimeString());
        $recent = $this->makeCertificate('2/1/2026/PNE', 'certificates/1/2-1-2026-PNE.pdf', now()->subDays(5)->toDateTimeString());

        $this->artisan('certificates:cleanup-pdfs')->assertSuccessful();

        Storage::disk('public')->assertMissing('certificates/1/1-1-2026-PNE.pdf');
        Storage::disk('public')->assertExists('certificates/1/2-1-2026-PNE.pdf');

        $this->assertNull($old->fresh()->file_path);
        $this->assertSame('1/1/2026/PNE', $old->fresh()->certificate_number);
        $this->assertSame('storage/certificates/1/2-1-2026-PNE.pdf', $recent->fresh()->file_path);
    }

    public function test_freezes_issue_date_from_generated_at_when_no_date_anywhere(): void
    {
        $course = $this->makeCourse();
        $cert = $this->makeCertificate('3/1/2026/PNE', 'certificates/1/3.pdf', '2026-03-10 15:49:51', ['course_id' => $course->id]);
        $this->assertNull($cert->issue_date);

        $this->artisan('certificates:cleanup-pdfs')->assertSuccessful();

        $this->assertSame('2026-03-10', $cert->fresh()->issue_date->toDateString());
        $this->assertNull($cert->fresh()->file_path);
    }

    public function test_does_not_overwrite_existing_issue_date(): void
    {
        $cert = $this->makeCertificate('4/1/2026/PNE', 'certificates/1/4.pdf', '2026-03-10 10:00:00', ['issue_date' => '2026-02-01']);

        $this->artisan('certificates:cleanup-pdfs')->assertSuccessful();

        $this->assertSame('2026-02-01', $cert->fresh()->issue_date->toDateString());
    }

    public function test_does_not_set_issue_date_when_course_has_certificate_date(): void
    {
        $course = $this->makeCourse(['issue_date_certyficates' => '2026-01-15']);
        $cert = $this->makeCertificate('5/1/2026/PNE', 'certificates/1/5.pdf', '2026-03-10 10:00:00', ['course_id' => $course->id]);

        $this->artisan('certificates:cleanup-pdfs')->assertSuccessful();

        $this->assertNull($cert->fresh()->issue_date);
        $this->assertNull($cert->fresh()->file_path);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $old = $this->makeCertificate('6/1/2026/PNE', 'certificates/1/6.pdf', now()->subDays(60)->toDateTimeString());

        $this->artisan('certificates:cleanup-pdfs --dry-run')
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();

        Storage::disk('public')->assertExists('certificates/1/6.pdf');
        $this->assertSame('storage/certificates/1/6.pdf', $old->fresh()->file_path);
        $this->assertNull($old->fresh()->issue_date);
    }

    public function test_skips_paths_outside_allowed_pattern(): void
    {
        $logo = $this->makeCertificate('7/1/2026/PNE', 'certificates/logos/logo.pdf', now()->subDays(90)->toDateTimeString());
        $traversal = $this->makeCertificate('8/1/2026/PNE', 'certificates/1/../../secret.pdf', now()->subDays(90)->toDateTimeString(), [], false);

        $this->artisan('certificates:cleanup-pdfs')->assertSuccessful();

        Storage::disk('public')->assertExists('certificates/logos/logo.pdf');
        $this->assertSame('storage/certificates/logos/logo.pdf', $logo->fresh()->file_path);
        $this->assertSame('storage/certificates/1/../../secret.pdf', $traversal->fresh()->file_path);
    }

    public function test_missing_file_on_disk_still_nulls_file_path(): void
    {
        $cert = $this->makeCertificate('10/1/2026/PNE', 'certificates/1/10.pdf', now()->subDays(60)->toDateTimeString(), [], false);

        $this->artisan('certificates:cleanup-pdfs')->assertSuccessful();

        $this->assertNull($cert->fresh()->file_path);
    }

    public function test_limit_restricts_number_of_processed_certificates(): void
    {
        $this->makeCertificate('11/1/2026/PNE', 'certificates/1/11.pdf', now()->subDays(60)->toDateTimeString());
        $this->makeCertificate('12/1/2026/PNE', 'certificates/1/12.pdf', now()->subDays(60)->toDateTimeString());
        $this->makeCertificate('13/1/2026/PNE', 'certificates/1/13.pdf', now()->subDays(60)->toDateTimeString());

        $this->artisan('certificates:cleanup-pdfs --limit=2')->assertSuccessful();

        $this->assertSame(1, Certificate::whereNotNull('file_path')->count());
    }

    public function test_refuses_too_small_days_value(): void
    {
        $cert = $this->makeCertificate('14/1/2026/PNE', 'certificates/1/14.pdf', now()->subDays(60)->toDateTimeString());

        $this->artisan('certificates:cleanup-pdfs --days=0')->assertFailed();

        Storage::disk('public')->assertExists('certificates/1/14.pdf');
        $this->assertNotNull($cert->fresh()->file_path);
    }

    public function test_orphans_removes_only_old_unreferenced_pdfs_in_course_folders(): void
    {
        $disk = Storage::disk('public');
        $this->makeCertificate('15/1/2026/PNE', 'certificates/1/referenced.pdf', now()->subDays(5)->toDateTimeString());
        $disk->put('certificates/1/orphan.pdf', 'x');
        $disk->put('certificates/online-7/orphan2.pdf', 'x');
        $disk->put('certificates/1/orphan-recent.pdf', 'x');
        $disk->put('certificates/logos/logo.png', 'x');
        $disk->put('certificates/backgrounds/bg.pdf', 'x');

        // mtime na dysku: "stare" pliki cofamy o 40 dni (referenced też, ale ma wiersz w bazie).
        $old = time() - 40 * 86400;
        foreach (['certificates/1/orphan.pdf', 'certificates/online-7/orphan2.pdf', 'certificates/1/referenced.pdf', 'certificates/logos/logo.png', 'certificates/backgrounds/bg.pdf'] as $f) {
            touch($disk->path($f), $old);
        }

        $this->artisan('certificates:cleanup-pdfs --orphans')->assertSuccessful();

        $disk->assertMissing('certificates/1/orphan.pdf');
        $disk->assertMissing('certificates/online-7/orphan2.pdf');
        $disk->assertExists('certificates/1/referenced.pdf');
        $disk->assertExists('certificates/1/orphan-recent.pdf');
        Storage::disk('public')->assertExists('certificates/logos/logo.png');
        Storage::disk('public')->assertExists('certificates/backgrounds/bg.pdf');
    }

    public function test_backfill_migration_sets_issue_date_only_where_missing_everywhere(): void
    {
        $courseWithDate = $this->makeCourse(['issue_date_certyficates' => '2026-01-15']);

        $needs = $this->makeCertificate('20/1/2026/PNE', 'certificates/1/20.pdf', '2026-03-10 15:49:51');
        $hasOwn = $this->makeCertificate('21/1/2026/PNE', 'certificates/1/21.pdf', '2026-03-10 10:00:00', ['issue_date' => '2026-02-01']);
        $courseDate = $this->makeCertificate('22/1/2026/PNE', 'certificates/1/22.pdf', '2026-03-10 10:00:00', ['course_id' => $courseWithDate->id]);
        $noFile = $this->makeCertificate('23/1/2026/PNE', 'certificates/1/23.pdf', '2026-03-10 10:00:00', ['file_path' => null], false);

        $migration = require database_path('migrations/2026_11_01_120000_backfill_certificates_issue_date_from_generated_at.php');
        $migration->up();

        $this->assertSame('2026-03-10', $needs->fresh()->issue_date->toDateString());
        $this->assertSame('2026-02-01', $hasOwn->fresh()->issue_date->toDateString());
        $this->assertNull($courseDate->fresh()->issue_date);
        $this->assertNull($noFile->fresh()->issue_date);
    }
}
