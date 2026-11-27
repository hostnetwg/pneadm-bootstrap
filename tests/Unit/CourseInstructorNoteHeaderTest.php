<?php

namespace Tests\Unit;

use App\Models\Course;
use Tests\TestCase;

class CourseInstructorNoteHeaderTest extends TestCase
{
    public function test_open_course_header_uses_szkolenie_and_start_date(): void
    {
        $course = new Course([
            'title' => 'Matematyka w praktyce',
            'category' => 'open',
            'start_date' => '2026-11-27 08:00:00',
        ]);

        $this->assertSame(
            'SZKOLENIE: Matematyka w praktyce (27.11.2026) ',
            $course->instructorNoteHeader()
        );
    }

    public function test_closed_course_header_uses_szkolenie_rp(): void
    {
        $course = new Course([
            'title' => 'Rada pedagogiczna',
            'category' => 'closed',
            'start_date' => '2026-03-02 10:00:00',
        ]);

        $this->assertSame(
            'SZKOLENIE RP: Rada pedagogiczna (02.03.2026) ',
            $course->instructorNoteHeader()
        );
    }

    public function test_header_without_date_says_brak_daty(): void
    {
        $course = new Course([
            'title' => 'Bez terminu',
            'category' => 'open',
        ]);

        $this->assertSame('SZKOLENIE: Bez terminu (brak daty) ', $course->instructorNoteHeader());
    }
}
