<?php

namespace Tests\Unit;

use App\Models\ClosedTrainingCase;
use Tests\TestCase;

class ClosedTrainingCaseEmailHtmlTest extends TestCase
{
    public function test_calendar_link_stays_single_encoded_when_the_mail_is_sanitized_again(): void
    {
        $url = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=Jak%20komunikowa%C4%87%20si%C4%99&dates=20261202T150000%2F20261202T180000&ctz=Europe%2FWarsaw';
        $once = ClosedTrainingCase::sanitizeSchoolEmailHtml('<a href="'.$url.'">Dodaj do kalendarza Google</a>');
        $twice = ClosedTrainingCase::sanitizeSchoolEmailHtml($once);

        $this->assertStringNotContainsString('&amp;amp;', $twice);
        $this->assertSame($once, $twice);
        $this->assertStringContainsString(
            $url,
            html_entity_decode($twice, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
    }
}
