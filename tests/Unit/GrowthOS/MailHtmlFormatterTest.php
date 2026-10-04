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

    public function test_templates_share_one_button_and_keep_the_editor_out_of_the_wrapper(): void
    {
        $hostile = '<table '.MailHtmlFormatter::MARKER.'><tr><td data-pne-mail-body="1"><p>Treść</p>'
            .'<a href="'.MaterialDraftTask::LINK_PLACEHOLDER.'">Zapisz się na webinar</a>'
            .'<script>alert(1)</script></td></tr></table>';

        $content = MailHtmlFormatter::editorContent($hostile);
        $this->assertStringNotContainsString(MailHtmlFormatter::MARKER, $content);
        $this->assertStringNotContainsString('<table', $content);
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString(MaterialDraftTask::LINK_PLACEHOLDER, $content);
        $this->assertStringContainsString('Treść', $content);

        foreach (['classic', 'personal', 'minimal'] as $template) {
            $html = MailHtmlFormatter::finalHtml($hostile, $template);
            $this->assertSame(1, substr_count($html, MailHtmlFormatter::MARKER));
            $this->assertSame(1, substr_count($html, 'href="'.MaterialDraftTask::LINK_PLACEHOLDER.'"'));
            $this->assertSame(1, substr_count($html, MailHtmlFormatter::CTA_LABEL));
            $this->assertStringNotContainsString('<script', $html);
            $this->assertStringContainsString('data-pne-mail-template="'.$template.'"', $html);
        }

        $this->assertStringContainsString('Spotkajmy się na kolejnym webinarze', MailHtmlFormatter::finalHtml('Treść', 'personal'));
        $this->assertStringContainsString('pnedu.pl', MailHtmlFormatter::finalHtml('Treść', 'minimal'));
        $this->assertStringContainsString('Praktyczna wiedza dla nauczycieli i dyrektorów', MailHtmlFormatter::finalHtml('Treść', 'classic'));
        $this->assertStringNotContainsString('SAMPLE', MailHtmlFormatter::finalHtml('Treść', 'classic'));
    }

    public function test_copy_prepends_the_preheader_and_plain_text_for_ai_drops_the_layout(): void
    {
        $html = MailHtmlFormatter::format("Dzień dobry,\n\nTreść zaproszenia.");
        $copied = MailHtmlFormatter::copyHtml('Krótki preheader.', $html, 'personal');

        $this->assertStringStartsWith('<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">Krótki preheader.', $copied);
        $this->assertSame(1, substr_count($copied, 'href="'.MaterialDraftTask::LINK_PLACEHOLDER.'"'));
        $this->assertStringContainsString('Spotkajmy się na kolejnym webinarze', $copied);

        $plain = MailHtmlFormatter::plainForAi("Temat: Temat\nPreheader: Krótki preheader.\n\n".$html);
        $this->assertStringNotContainsString(MailHtmlFormatter::MARKER, $plain);
        $this->assertStringNotContainsString('<table', $plain);
        $this->assertStringNotContainsString('style=', $plain);
        $this->assertStringNotContainsString(MailHtmlFormatter::CTA_LABEL, $plain);
        $this->assertStringContainsString('Temat: Temat', $plain);
        $this->assertStringContainsString('Preheader: Krótki preheader.', $plain);
        $this->assertStringContainsString('Dzień dobry,', $plain);
        $this->assertStringContainsString('Treść zaproszenia.', $plain);
        $this->assertStringNotContainsString('Spotkajmy się na kolejnym webinarze', $plain);
    }

    public function test_plain_editor_content_is_stored_unchanged(): void
    {
        $plain = "Dzień dobry,\n\nTreść.";

        $this->assertSame($plain, MailHtmlFormatter::editorContent($plain));
        $this->assertSame(
            "Temat: Temat\nPreheader: Zdanie.\n\n".$plain,
            MailHtmlFormatter::plainForAi("Temat: Temat\nPreheader: Zdanie.\n\n".$plain),
        );
    }
}
