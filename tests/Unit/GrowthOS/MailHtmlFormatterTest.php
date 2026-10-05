<?php

namespace Tests\Unit\GrowthOS;

use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Support\GrowthOS\MailHtmlFormatter;
use App\Support\GrowthOS\MailRenderContext;
use App\Support\GrowthOS\MailTemplates;
use PHPUnit\Framework\TestCase;

class MailHtmlFormatterTest extends TestCase
{
    public function test_plain_mail_becomes_sendy_pne_with_greeting_and_button(): void
    {
        $context = new MailRenderContext(
            registrationUrl: 'https://pnedu.pl/courses/577',
            webinarTitle: 'Canva AI',
            liveLabel: 'wtorek 6 października, godz. 20:00',
            hostName: 'Waldemar Grabowski',
        );
        $html = MailHtmlFormatter::format(
            "zapraszamy Państwa.\n\n- Punkt pierwszy\n- Punkt drugi\n\nZ pozdrowieniami,\nZespół PNE",
            null,
            $context,
        );

        $this->assertStringContainsString(MailHtmlFormatter::MARKER, $html);
        $this->assertStringContainsString('max-width:680px', $html);
        $this->assertStringContainsString(MailHtmlFormatter::GREETING, $html);
        $this->assertStringContainsString('data-pne-mail-template="sendy-pne"', $html);
        $this->assertStringContainsString('<li style="margin:0 0 8px;">Punkt pierwszy</li>', $html);
        $this->assertStringContainsString('href="https://pnedu.pl/courses/577"', $html);
        $this->assertStringContainsString(MailHtmlFormatter::CTA_LABEL, $html);
        $this->assertStringContainsString('[unsubscribe]', $html);
        $this->assertStringContainsString('Akredytowany Niepubliczny Ośrodek Doskonalenia Nauczycieli', $html);
        $this->assertStringContainsString('Platforma Nowoczesnej Edukacji', $html);
        $this->assertStringContainsString('Canva AI', $html);
        $this->assertStringContainsString('TERMIN', $html);
        $this->assertStringContainsString('wtorek 6 października, godz. 20:00', $html);
        $this->assertStringContainsString('PROWADZĄCY', $html);
        $this->assertStringContainsString('Waldemar Grabowski', $html);
        $this->assertTrue(
            strpos($html, 'Canva AI') < strpos($html, MailHtmlFormatter::CTA_LABEL),
        );
        $this->assertStringNotContainsString('<strong>Temat:</strong>', $html);
        $this->assertStringNotContainsString('Zapisz się:', $html);
    }

    public function test_markup_in_the_plain_text_is_escaped_and_an_html_draft_is_not_wrapped_again(): void
    {
        $html = MailHtmlFormatter::format('Cena <b>0 zł</b> & rabat');
        $this->assertStringContainsString('Cena &lt;b&gt;0 zł&lt;/b&gt; &amp; rabat', $html);

        $again = MailHtmlFormatter::formatComposedDraft("Temat: Temat\nPreheader: Zdanie.\n\n".$html);
        $this->assertSame(1, substr_count($again, MailHtmlFormatter::MARKER));
    }

    public function test_legacy_template_keys_render_as_sendy_pne(): void
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

        foreach (['classic', 'personal', 'minimal', 'sendy-pne'] as $template) {
            $html = MailHtmlFormatter::finalHtml($hostile, $template, new MailRenderContext(
                registrationUrl: 'https://pnedu.pl/courses/1',
                webinarTitle: 'Test',
            ));
            $this->assertSame(1, substr_count($html, MailHtmlFormatter::MARKER));
            $this->assertSame(1, substr_count($html, MailHtmlFormatter::CTA_LABEL));
            $this->assertStringNotContainsString('<script', $html);
            $this->assertStringContainsString('data-pne-mail-template="sendy-pne"', $html);
            $this->assertSame(MailTemplates::CANONICAL, MailTemplates::key($template));
        }
    }

    public function test_copy_prepends_the_preheader_and_plain_text_for_ai_drops_the_layout(): void
    {
        $context = new MailRenderContext(
            registrationUrl: 'https://pnedu.pl/courses/577',
            youtubeLiveUrl: 'https://www.youtube.com/live/abc',
            webinarTitle: 'Canva AI',
            showCertificate: true,
        );
        $html = MailHtmlFormatter::format('zapraszamy Państwa na webinar.', null, $context);
        $copied = MailHtmlFormatter::copyHtml('Krótki preheader.', $html, null, $context);

        $this->assertStringStartsWith('<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">Krótki preheader.', $copied);
        $this->assertStringContainsString('href="https://pnedu.pl/courses/577"', $copied);
        $this->assertStringContainsString(MailHtmlFormatter::YOUTUBE_CTA_LABEL, $copied);
        $this->assertStringContainsString('bezpłatnego zaświadczenia', $copied);
        $this->assertStringContainsString('&#128220;', $copied);
        $this->assertStringContainsString('ZAŚWIADCZENIE', $copied);
        $this->assertStringContainsString('obecnych na żywo', $copied);
        $this->assertStringContainsString('bądź z nami', $copied);
        $this->assertStringContainsString('Canva AI', $copied);
        $ctaPos = strpos($copied, MailHtmlFormatter::CTA_LABEL);
        $youtubePos = strpos($copied, MailHtmlFormatter::YOUTUBE_CTA_LABEL);
        $certPos = strpos($copied, 'bezpłatnego zaświadczenia');
        $titlePos = strpos($copied, 'Canva AI');
        $this->assertNotFalse($ctaPos);
        $this->assertNotFalse($youtubePos);
        $this->assertNotFalse($certPos);
        $this->assertNotFalse($titlePos);
        $this->assertTrue($titlePos < $certPos);
        $this->assertTrue($certPos < $ctaPos);
        $this->assertTrue($ctaPos < $youtubePos);
        $this->assertTrue(MailHtmlFormatter::canCopyHtml($context));

        $plain = MailHtmlFormatter::plainForAi("Temat: Temat\nPreheader: Krótki preheader.\n\n".$html);
        $this->assertStringNotContainsString(MailHtmlFormatter::MARKER, $plain);
        $this->assertStringNotContainsString('<table', $plain);
        $this->assertStringNotContainsString(MailHtmlFormatter::CTA_LABEL, $plain);
        $this->assertStringContainsString('Temat: Temat', $plain);
        $this->assertStringContainsString('zapraszamy Państwa na webinar.', $plain);
    }

    public function test_missing_youtube_omits_button_and_reminder_room_marker_becomes_cta(): void
    {
        $withoutYoutube = MailHtmlFormatter::finalHtml('Treść', null, new MailRenderContext(
            registrationUrl: 'https://pnedu.pl/courses/1',
            webinarTitle: 'Test',
        ));
        $this->assertStringNotContainsString(MailHtmlFormatter::YOUTUBE_CTA_LABEL, $withoutYoutube);

        $reminder = MailHtmlFormatter::finalHtml(
            "Przypomnienie\n\n".MaterialDraftTask::ROOM_LINK_PLACEHOLDER."\n\n".MaterialDraftTask::LINK_PLACEHOLDER,
            null,
            new MailRenderContext(
                registrationUrl: 'https://pnedu.pl/courses/1',
                webinarTitle: 'Test',
                isReminder: true,
            ),
        );
        $this->assertStringContainsString(MailHtmlFormatter::ROOM_CTA_LABEL, $reminder);
        $this->assertStringNotContainsString('>'.MaterialDraftTask::ROOM_LINK_PLACEHOLDER.'<', $reminder);
        $this->assertFalse(MailHtmlFormatter::canCopyHtml(new MailRenderContext));
    }

    public function test_reminder_same_day_shows_urgency_banner(): void
    {
        $html = MailHtmlFormatter::finalHtml('Treść przypomnienia.', null, new MailRenderContext(
            registrationUrl: 'https://pnedu.pl/courses/1',
            webinarTitle: 'Test',
            isReminder: true,
            reminderTiming: 'same_day',
        ));

        $this->assertStringContainsString('data-pne-mail-reminder-banner', $html);
        $this->assertStringContainsString('OSTATNIE PRZYPOMNIENIE', $html);
        $this->assertStringContainsString('DZISIAJ', $html);
    }

    public function test_reminder_day_before_shows_jutro_banner(): void
    {
        $html = MailHtmlFormatter::finalHtml('Treść.', null, new MailRenderContext(
            registrationUrl: 'https://pnedu.pl/courses/1',
            webinarTitle: 'Test',
            isReminder: true,
            reminderTiming: 'day_before',
        ));

        $this->assertStringContainsString('WEBINAR JUŻ JUTRO', $html);
    }
}
