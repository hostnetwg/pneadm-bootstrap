<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantEmailNormalizationTest extends TestCase
{
    use RefreshDatabase;
    public function test_normalize_email_trims_and_lowercases(): void
    {
        $this->assertSame('jan@example.com', Participant::normalizeEmail('  JAN@Example.com  '));
    }

    public function test_normalize_email_returns_null_for_empty(): void
    {
        $this->assertNull(Participant::normalizeEmail('   '));
        $this->assertNull(Participant::normalizeEmail(null));
    }

    public function test_find_duplicate_in_course_matches_normalized_email(): void
    {
        $instructor = Instructor::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan.kowalski@example.test',
            'is_active' => true,
        ]);
        $course = Course::create([
            'title' => 'Szkolenie testowe',
            'description' => 'Opis',
            'start_date' => now()->subDays(2),
            'end_date' => now()->subDay(),
            'is_paid' => false,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
        ]);

        $existing = Participant::create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Waldemar',
            'last_name' => 'Grabowski',
            'email' => 'Jan@Example.com',
        ]);

        $duplicate = Participant::findDuplicateInCourse($course->id, '  jan@example.com  ');
        $this->assertNotNull($duplicate);
        $this->assertTrue($duplicate->is($existing));

        $this->assertNull(Participant::findDuplicateInCourse($course->id, 'inny@example.com'));
        $this->assertNull(Participant::findDuplicateInCourse($course->id, null));
        $this->assertNull(Participant::findDuplicateInCourse($course->id, 'jan@example.com', $existing->id));
    }
}
