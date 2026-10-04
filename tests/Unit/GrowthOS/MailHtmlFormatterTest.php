<?php

namespace Tests\Unit\GrowthOS;

use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Support\GrowthOS\MailHtmlFormatter;
use PHPUnit\Framework\TestCase;

class MailHtmlFormatterTest extends TestCase
{
    public function test_plain_mail_becomes_a_table_with_a_list_and_a_button(): void
    {
        $html = MailHtmlFormatter::format("Dzień dobry,\n\n- Punkt pierwszy\n- Punkt drugi\n\nZapisz się:\n".MaterialDraftTask::LINK_PLACEHOLDER."\n\nZ pozdrowieniami,\nZespół PNE");

        $this->assertStringContainsString(MailHtmlFormatter::MARKER, $html);
        $this->assertStringContainsString('max-width:600px', $html);
        $this->assertStringContainsString('Dzień dobry,', $html);
        $this->assertStringContainsString('<li style="margin:0 0 8px;">Punkt pierwszy</li>', $html);
        $this->assertStringContainsString('href="'.MaterialDraftTask::LINK_PLACEHOLDER.'"', $html);
        $this->assertStringContainsString('Zapisz się na webinar', $html);
        $this->assertStringNotContainsString('Zapisz się:', $html);
        $this->assertStringContainsString('Zespół PNE', $html);
    }

    public function test_markup_in_the_plain_text_is_escaped_and_an_html_draft_is_not_wrapped_again(): void
    {
        $html = MailHtmlFormatter::format('Cena <b>0 zł</b> & rabat');
        $this->assertStringContainsString('Cena &lt;b&gt;0 zł&lt;/b&gt; &amp; rabat', $html);

        $again = MailHtmlFormatter::formatComposedDraft("Temat: Temat\nPreheader: Zdanie.\n\n".$html);
        $this->assertSame(1, substr_count($again, MailHtmlFormatter::MARKER));
    }
}
