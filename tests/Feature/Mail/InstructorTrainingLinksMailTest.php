<?php

namespace Tests\Feature\Mail;

use App\Mail\InstructorTrainingLinksMail;
use App\Models\Course;
use Tests\TestCase;

class InstructorTrainingLinksMailTest extends TestCase
{
    public function test_instructor_training_links_mail_uses_system_sender_and_contains_no_legacy_domains(): void
    {
        config([
            'mail.system.mailer' => 'ses',
            'mail.system.from_address' => 'info@system.pnedu.pl',
            'mail.system.from_name' => 'Platforma Nowoczesnej Edukacji',
            'mail.system.reply_to_address' => 'kontakt@pnedu.pl',
            'mail.system.reply_to_name' => 'Platforma Nowoczesnej Edukacji',
        ]);

        $mail = (new InstructorTrainingLinksMail(
            course: new Course(['title' => 'Testowe szkolenie']),
            plainBody: "Dzień dobry,\n\nLink do materiałów: https://pnedu.pl/dashboard\n",
            subjectLine: 'Linki do szkolenia'
        ))->build();

        $this->assertSame('ses', $mail->mailer);
        $this->assertSame('info@system.pnedu.pl', $mail->from[0]['address']);
        $this->assertSame('Platforma Nowoczesnej Edukacji', $mail->from[0]['name']);
        $this->assertSame('kontakt@pnedu.pl', $mail->replyTo[0]['address']);
        $this->assertSame('Platforma Nowoczesnej Edukacji', $mail->replyTo[0]['name']);

        $html = $mail->render();

        $this->assertStringNotContainsString('kontakt@nowoczesna-edukacja.pl', $html);
        $this->assertStringNotContainsString('nowoczesna-edukacja.pl', $html);
        $this->assertStringNotContainsString('zdalna-lekcja.pl', $html);
        $this->assertStringContainsString('https://pnedu.pl/dashboard', $html);
    }
}
