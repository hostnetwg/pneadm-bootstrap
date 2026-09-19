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
    public function test_includes_attendance_link_when_certificate_registration_is_enabled(): void
    {
        config(['services.pnedu_frontend_url' => 'http://edu.localhost:8081']);

        $course = new Course([
            'title' => 'Test szkolenie',
            'certificate_registration_open' => true,
            'certificate_registration_token' => 'token-xyz',
            'certificate_registration_starts_at' => now()->addDay(),
            'certificate_registration_ends_at' => now()->addDays(2),
        ]);
        $course->setRelation('videos', collect());
        $course->setRelation('fileLinks', collect());
        $course->setRelation('surveyLinks', collect());

        $body = CourseInstructorLinksEmailBody::build($course);

        $this->assertStringContainsString(
            '1) Lista obecności / zaświadczenie: http://edu.localhost:8081/certificate-registration/token-xyz',
            $body
        );
        $this->assertStringNotContainsString('LISTA OBECNOŚCI:', $body);
    }

    public function test_omits_attendance_link_when_registration_is_disabled(): void
    {
        config(['services.pnedu_frontend_url' => 'http://edu.localhost:8081']);

        $course = new Course([
            'title' => 'Test szkolenie',
            'certificate_registration_open' => false,
            'certificate_registration_token' => 'token-xyz',
        ]);
        $course->setRelation('videos', collect());
        $course->setRelation('fileLinks', collect());
        $course->setRelation('surveyLinks', collect());

        $body = CourseInstructorLinksEmailBody::build($course);

        $this->assertStringNotContainsString('Lista obecności / zaświadczenie', $body);
        $this->assertStringNotContainsString('certificate-registration/token-xyz', $body);
    }

    public function test_attendance_link_is_the_first_numbered_item(): void
    {
        config(['services.pnedu_frontend_url' => 'http://edu.localhost:8081']);

        $course = new Course([
            'title' => 'Test',
            'certificate_registration_open' => true,
            'certificate_registration_token' => 'tok',
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

        $this->assertStringContainsString(
            '1) Lista obecności / zaświadczenie: http://edu.localhost:8081/certificate-registration/tok',
            $body
        );
        $this->assertStringContainsString('2) MATERIAŁY: https://example.com/files', $body);
        $this->assertStringContainsString('3) ANKIETA:', $body);

        $listaPos = strpos($body, 'Lista obecności / zaświadczenie');
        $materialyPos = strpos($body, 'MATERIAŁY:');
        $ankietaPos = strpos($body, 'ANKIETA:');
        $this->assertNotFalse($listaPos);
        $this->assertNotFalse($materialyPos);
        $this->assertNotFalse($ankietaPos);
        $this->assertLessThan($materialyPos, $listaPos);
        $this->assertLessThan($ankietaPos, $listaPos);
    }

    public function test_survey_and_materials_use_url_only_without_titles(): void
    {
        config(['services.pnedu_frontend_url' => 'https://pnedu.pl']);

        $course = new Course([
            'title' => 'Zmiany w prawie oświatowym',
            'start_date' => Carbon::parse('2026-08-10 09:00:00'),
        ]);
        $course->setRelation('videos', collect());
        $course->setRelation('fileLinks', collect([
            new CourseFileLink([
                'url' => 'https://drive.google.com/file/abc',
                'title' => 'Pakiet PDF – długi tytuł',
                'order' => 1,
            ]),
        ]));
        $course->setRelation('surveyLinks', collect([
            new CourseSurveyLink([
                'title' => 'ANKIETA: Zmiany w prawie oświatowym (2026-08-10)',
                'order' => 1,
                'public_token' => 'advicsdaqvltun98dc0uqnjddpoj8fd5yjt7ig5n',
                'is_active' => true,
            ]),
        ]));

        $body = CourseInstructorLinksEmailBody::build($course);

        $this->assertStringContainsString(
            '1) MATERIAŁY: https://drive.google.com/file/abc',
            $body
        );
        $this->assertStringNotContainsString('Pakiet PDF', $body);
        $this->assertStringContainsString(
            '2) ANKIETA: https://pnedu.pl/ankieta/advicsdaqvltun98dc0uqnjddpoj8fd5yjt7ig5n',
            $body
        );
        $this->assertStringNotContainsString(
            'ANKIETA: Zmiany w prawie oświatowym (2026-08-10):',
            $body
        );
    }
}
