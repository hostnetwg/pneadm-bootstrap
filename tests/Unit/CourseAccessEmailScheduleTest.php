<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Support\CourseAccessEmailSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAccessEmailScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefixed_start_line_includes_duration_without_end_datetime(): void
    {
        $course = $this->courseWithSchedule(
            start: '2026-07-20 10:00:00',
            end: '2026-07-20 12:00:00',
        );

        $this->assertSame(
            'Data rozpoczęcia: 20.07.2026 10:00 (2 godz.)',
            CourseAccessEmailSchedule::prefixedStartLine($course, hideWhenPast: false)
        );
    }

    public function test_prefixed_start_line_omits_duration_when_end_date_missing(): void
    {
        $course = new Course([
            'start_date' => Carbon::parse('2026-07-20 10:00:00', 'Europe/Warsaw'),
            'end_date' => null,
        ]);

        $this->assertSame(
            'Data rozpoczęcia: 20.07.2026 10:00',
            CourseAccessEmailSchedule::prefixedStartLine($course, hideWhenPast: false)
        );
    }

    public function test_prefixed_start_line_hidden_after_course_end(): void
    {
        Carbon::setTestNow('2026-08-01 12:00:00');

        $course = $this->courseWithSchedule(
            start: '2026-07-20 10:00:00',
            end: '2026-07-20 12:00:00',
        );

        $this->assertNull(CourseAccessEmailSchedule::prefixedStartLine($course));
    }

    public function test_sentence_fragment_uses_polish_date_and_duration(): void
    {
        $course = $this->courseWithSchedule(
            start: '2026-08-21 15:00:00',
            end: '2026-08-21 17:30:00',
        );

        $this->assertSame(
            '21 sierpnia 2026 r. o godz. 15:00 (2 godz. 30 min.)',
            CourseAccessEmailSchedule::sentenceFragment($course)
        );
    }

    private function courseWithSchedule(string $start, ?string $end): Course
    {
        return Course::query()->create([
            'title' => 'Termin test',
            'description' => 'Opis',
            'start_date' => Carbon::parse($start, 'Europe/Warsaw'),
            'end_date' => $end ? Carbon::parse($end, 'Europe/Warsaw') : null,
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/PNE',
        ]);
    }
}
