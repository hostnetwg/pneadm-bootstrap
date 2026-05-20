<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CourseFileLink;
use App\Models\CourseSurveyLink;
use App\Support\CourseInstructorLinksEmailBody;
use Carbon\Carbon;
use Tests\TestCase;

class CourseInstructorLinksEmailBodyTest extends TestCase
{
    public function test_includes_attendance_list_when_certificate_registration_is_in_window(): void
    {
        config(['services.pnedu_frontend_url' => 'http://edu.localhost:8081']);

        $course = new Course([
            'title' => 'Test szkolenie',
            'certificate_registration_open' => true,
            'certificate_registration_token' => 'token-xyz',
            'certificate_registration_starts_at' => now()->subHour(),
            'certificate_registration_ends_at' => now()->addHour(),
        ]);
        $course->setRelation('videos', collect());
        $course->setRelation('fileLinks', collect());
        $course->setRelation('surveyLinks', collect());

        $body = CourseInstructorLinksEmailBody::build($course);

        $this->assertStringContainsString('LISTA OBECNOŚCI:', $body);
        $this->assertStringContainsString(
            'http://edu.localhost:8081/certificate-registration/token-xyz',
            $body
        );
    }

    public function test_omits_attendance_list_when_registration_closed_or_outside_window(): void
    {
        config(['services.pnedu_frontend_url' => 'http://edu.localhost:8081']);

        $frozenNow = Carbon::parse('2026-05-20 12:00:00');
        Carbon::setTestNow($frozenNow);

        try {
            $course = new Course([
                'title' => 'Test szkolenie',
                'certificate_registration_open' => true,
                'certificate_registration_token' => 'token-xyz',
                'certificate_registration_starts_at' => $frozenNow->copy()->addDay(),
                'certificate_registration_ends_at' => $frozenNow->copy()->addDays(2),
            ]);
            $course->setRelation('videos', collect());
            $course->setRelation('fileLinks', collect());
            $course->setRelation('surveyLinks', collect());

            $body = CourseInstructorLinksEmailBody::build($course);

            $this->assertStringNotContainsString('LISTA OBECNOŚCI:', $body);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_attendance_list_appears_before_survey_section(): void
    {
        config(['services.pnedu_frontend_url' => 'http://edu.localhost:8081']);

        $course = new Course([
            'title' => 'Test',
            'certificate_registration_open' => true,
            'certificate_registration_token' => 'tok',
            'certificate_registration_starts_at' => now()->subMinute(),
            'certificate_registration_ends_at' => now()->addMinute(),
        ]);
        $course->setRelation('videos', collect());
        $course->setRelation('fileLinks', collect([
            new CourseFileLink(['url' => 'https://example.com/files', 'title' => 'Materiały', 'order' => 1]),
        ]));
        $course->setRelation('surveyLinks', collect([
            new CourseSurveyLink([
                'title' => 'Ankieta',
                'order' => 1,
                'public_token' => 'surveytok',
                'is_active' => true,
            ]),
        ]));

        $body = CourseInstructorLinksEmailBody::build($course);

        $listaPos = strpos($body, 'LISTA OBECNOŚCI:');
        $ankietaPos = strpos($body, 'ANKIETA:');
        $this->assertNotFalse($listaPos);
        $this->assertNotFalse($ankietaPos);
        $this->assertLessThan($ankietaPos, $listaPos);
    }
}
