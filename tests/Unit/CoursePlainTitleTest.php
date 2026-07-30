<?php

namespace Tests\Unit;

use App\Models\Course;
use Tests\TestCase;

class CoursePlainTitleTest extends TestCase
{
    public function test_plain_title_strips_nbsp_entities_and_tags(): void
    {
        $course = new Course([
            'title' => 'Nowe przepisy żywieniowe w szkole i&nbsp;przedszkolu, obowiązujące od 1 września 2026&nbsp;r.',
        ]);

        $this->assertSame(
            'Nowe przepisy żywieniowe w szkole i przedszkolu, obowiązujące od 1 września 2026 r.',
            $course->plainTitle()
        );
    }

    public function test_plain_title_collapses_decoded_nbsp_and_html(): void
    {
        $course = new Course([
            'title' => '<strong>Szkolenie</strong> w'.html_entity_decode('&nbsp;', ENT_HTML5, 'UTF-8').'Warszawie',
        ]);

        $this->assertSame('Szkolenie w Warszawie', $course->plainTitle());
    }

    public function test_plain_title_uses_fallback_when_empty(): void
    {
        $course = new Course(['title' => ' &nbsp; ']);

        $this->assertSame('szkolenie', $course->plainTitle());
        $this->assertSame('Kurs', $course->plainTitle('Kurs'));
    }
}
