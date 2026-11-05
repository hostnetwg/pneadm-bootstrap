<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ParticipantDataDocxExportTest extends TestCase
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

    public function test_participants_page_shows_docx_export_button(): void
    {
        $user = User::factory()->create();
        $course = $this->createCourse();

        $response = $this->actingAs($user)->get(route('participants.index', $course));

        $response->assertOk();
        $response->assertSee('Pobierz rejestr zaświadczeń', false);
        $response->assertSee('Pobierz listę uczestników z danymi .docx', false);
        $response->assertSee(route('participants.download-data-docx', $course), false);
    }

    public function test_docx_export_contains_course_header_participants_and_compliance_note(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required to inspect DOCX files.');
        }

        $user = User::factory()->create();
        $course = $this->createCourse();

        Participant::query()->create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Anna',
            'last_name' => 'Kowalska',
            'email' => 'anna@example.test',
            'birth_date' => '1985-05-12',
            'birth_place' => 'Warszawa',
        ]);

        $response = $this->actingAs($user)->get(route('participants.download-data-docx', $course));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.docx', $response->headers->get('Content-Disposition'));

        $documentXml = $this->readDocumentXml($response->getContent());

        $this->assertStringContainsString('Lista uczestników szkolenia', $documentXml);
        $this->assertStringContainsString('Szkolenie testowe', $documentXml);
        $this->assertStringContainsString('mgr Jan Nowak', $documentXml);
        $this->assertStringContainsString('Anna', $documentXml);
        $this->assertStringContainsString('Kowalska', $documentXml);
        $this->assertStringContainsString('12.05.1985', $documentXml);
        $this->assertStringContainsString('Warszawa', $documentXml);
        $this->assertStringContainsString('anna@example.test', $documentXml);
        $this->assertStringContainsString('Adres e-mail nie jest obowiązkowy', $documentXml);
        $this->assertStringContainsString('RODO', $documentXml);
        $this->assertStringContainsString('bez odrębnej, dobrowolnej zgody', $documentXml);
        $this->assertSame(12, substr_count($documentXml, '<w:tr>')); // nagłówek + uczestnik + 10 pustych wierszy
    }

    private function createCourse(): Course
    {
        $instructor = Instructor::query()->create([
            'title' => 'mgr',
            'first_name' => 'Jan',
            'last_name' => 'Nowak',
            'email' => 'jan.nowak@example.test',
            'is_active' => true,
        ]);

        $start = now()->addDay()->setTime(10, 0);

        return Course::query()->create([
            'title' => 'Szkolenie testowe',
            'description' => 'Test',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(2),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
            'certificate_format' => '{nr}/PNE',
        ]);
    }

    private function readDocumentXml(string $docxContent): string
    {
        $path = tempnam(sys_get_temp_dir(), 'test-docx-');
        file_put_contents($path, $docxContent);

        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);

        $this->assertIsString($xml);

        return $xml;
    }
}
