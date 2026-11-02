<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Instructor;
use App\Services\Certificate\CertificateTemplateVariableResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateLegacyEventTextTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_02_120000_add_start_date_variables_to_legacy_certificate_event_text.php';

    public function test_migration_appends_start_date_and_duration_only_to_text_without_variables(): void
    {
        $legacy = $this->createTemplate(6, 'zorganizowanym w dniu');
        $plural = $this->createTemplate(9, 'zorganizowane w dniu');
        $custom = $this->createTemplate(7, 'w dniu {data_konca} r.');
        $default = $this->createTemplate(5, 'zorganizowanym w dniu {data_konca} r. {czas_trwania} przez');
        $online = $this->createTemplate(10, 'zrealizowany w dniu {data_zakonczenia} r. {data_konca}');

        $migration = require base_path(self::MIGRATION);
        $migration->up();
        $migration->up();

        $this->assertSame('zorganizowanym w dniu {data_rozpoczecia} r. {czas_trwania}przez', $this->eventText($legacy));
        $this->assertSame('zorganizowane w dniu {data_rozpoczecia} r. {czas_trwania}przez', $this->eventText($plural));
        $this->assertSame('w dniu {data_konca} r.', $this->eventText($custom));
        $this->assertSame('zorganizowanym w dniu {data_rozpoczecia} r. {czas_trwania} przez', $this->eventText($default));
        $this->assertSame('zrealizowany w dniu {data_zakonczenia} r. {data_konca}', $this->eventText($online));

        $migration->down();

        $this->assertSame('zorganizowanym w dniu', $this->eventText($legacy));
        $this->assertSame('w dniu {data_konca} r.', $this->eventText($custom));
        $this->assertSame('zorganizowanym w dniu {data_konca} r. {czas_trwania} przez', $this->eventText($default));
    }

    public function test_migrated_text_renders_like_before_regression(): void
    {
        $course = (object) ['start_date' => '2022-03-12 18:00:00', 'end_date' => '2022-03-12 20:00:00'];
        $resolver = app(CertificateTemplateVariableResolver::class);
        $text = 'zorganizowanym w dniu {data_rozpoczecia} r. {czas_trwania}przez';

        $this->assertSame(
            'zorganizowanym w dniu 12.03.2022 r. w wymiarze 120 minut, przez',
            $resolver->resolveEventText($text, ['course' => $course, 'duration_minutes' => 120, 'show_duration' => true])
        );
        $this->assertSame(
            'zorganizowanym w dniu 12.03.2022 r. przez',
            $resolver->resolveEventText($text, ['course' => $course, 'duration_minutes' => 120, 'show_duration' => false])
        );
    }

    public function test_command_deletes_only_files_generated_since_regression(): void
    {
        Storage::fake('public');
        $template = $this->createTemplate(6, 'zorganizowanym w dniu');
        $course = $this->makeCourse($template->id);

        $broken = $this->makeCertificate($course, '1/'.$course->id.'/2022/TIK', '2026-08-26 20:51:00');
        $before = $this->makeCertificate($course, '2/'.$course->id.'/2022/TIK', '2026-06-01 10:00:00');

        $this->artisan('certificates:audit-event-text')->assertSuccessful();
        Storage::disk('public')->assertExists($this->relative($broken));

        $this->artisan('certificates:audit-event-text', ['--delete-files' => true, '--force' => true])->assertSuccessful();

        Storage::disk('public')->assertMissing($this->relative($broken));
        Storage::disk('public')->assertExists($this->relative($before));
        $this->assertNull($broken->fresh()->file_path);
        $this->assertSame('2026-08-26', $broken->fresh()->issue_date->toDateString());
        $this->assertNotNull($before->fresh()->file_path);
    }

    private function createTemplate(int $id, string $eventText): CertificateTemplate
    {
        $template = new CertificateTemplate([
            'name' => 'Szablon '.$id,
            'slug' => 'szablon-'.$id,
            'config' => ['blocks' => [
                'block_0' => ['type' => 'header', 'order' => 0, 'config' => ['title' => 'ZAŚWIADCZENIE']],
                'block_2' => ['type' => 'course_info', 'order' => 2, 'config' => ['event_text' => $eventText, 'show_duration' => '1']],
            ]],
            'is_active' => true,
            'is_default' => false,
        ]);
        $template->id = $id;
        $template->save();

        return $template;
    }

    private function eventText(CertificateTemplate $template): string
    {
        return $template->fresh()->config['blocks']['block_2']['config']['event_text'];
    }

    private function makeCourse(int $templateId): Course
    {
        $instructor = Instructor::create([
            'first_name' => 'Jan',
            'last_name' => 'Prowadzący',
            'email' => 'jan.event-text@example.test',
            'is_active' => true,
        ]);

        return Course::create([
            'title' => 'Webinar TIK',
            'description' => 'Opis',
            'start_date' => '2022-03-12 18:00:00',
            'end_date' => '2022-03-12 20:00:00',
            'is_paid' => false,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/TIK',
            'next_participant_order' => 1,
            'certificate_template_id' => $templateId,
        ]);
    }

    private function makeCertificate(Course $course, string $number, string $generatedAt): Certificate
    {
        $path = 'certificates/'.$course->id.'/'.str_replace('/', '-', $number).'.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 test');

        $certificate = new Certificate([
            'course_id' => $course->id,
            'certificate_number' => $number,
            'file_path' => 'storage/'.$path,
            'generated_at' => $generatedAt,
        ]);
        $certificate->save();

        return $certificate->fresh();
    }

    private function relative(Certificate $certificate): string
    {
        return substr($certificate->file_path, strlen('storage/'));
    }
}
