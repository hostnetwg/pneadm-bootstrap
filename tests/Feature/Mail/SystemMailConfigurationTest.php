<?php

namespace Tests\Feature\Mail;

use App\Mail\CertificateLinkMail;
use App\Mail\CertificateSingleLinkMail;
use App\Mail\CourseAccessMail;
use App\Mail\DataCompletionRequestMail;
use App\Mail\InstructorTrainingLinksMail;
use App\Models\Course;
use App\Models\DataCompletionToken;
use App\Models\Participant;
use App\Notifications\PneduFormOrderProvisionedExistingUser;
use App\Notifications\PneduFormOrderProvisionedNewUser;
use App\Notifications\PneduFrontendResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SystemMailConfigurationTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.system.mailer' => 'ses',
            'mail.system.from_address' => 'info@system.pnedu.pl',
            'mail.system.from_name' => 'Platforma Nowoczesnej Edukacji',
            'mail.system.reply_to_address' => 'kontakt@pnedu.pl',
            'mail.system.reply_to_name' => 'Platforma Nowoczesnej Edukacji',
            'mail.brand.public_url' => 'https://pnedu.pl',
            'mail.brand.public_label' => 'www.pnedu.pl',
        ]);
    }

    /**
     * @param  \Illuminate\Mail\Mailable  $mail
     */
    protected function assertSystemMailHeaders($mail): void
    {
        $built = $mail->build();

        $this->assertSame('ses', $built->mailer);
        $this->assertSame('info@system.pnedu.pl', $built->from[0]['address']);
        $this->assertSame('Platforma Nowoczesnej Edukacji', $built->from[0]['name']);
        $this->assertSame('kontakt@pnedu.pl', $built->replyTo[0]['address']);
        $this->assertSame('Platforma Nowoczesnej Edukacji', $built->replyTo[0]['name']);
    }

    protected function assertNoLegacyDomains(string $html): void
    {
        $this->assertStringNotContainsString('kontakt@nowoczesna-edukacja.pl', $html);
        $this->assertStringNotContainsString('biuro@nowoczesna-edukacja.pl', $html);
        $this->assertStringNotContainsString('nowoczesna-edukacja.pl', $html);
    }

    public function test_instructor_training_links_mail_uses_system_mailer(): void
    {
        $mail = new InstructorTrainingLinksMail(
            course: new Course(['title' => 'Test']),
            plainBody: "Link: https://pnedu.pl\n",
            subjectLine: 'Linki'
        );

        $this->assertSystemMailHeaders($mail);
        $this->assertNoLegacyDomains($mail->build()->render());
    }

    public function test_course_access_mail_uses_system_mailer(): void
    {
        $mail = new CourseAccessMail(
            participant: new Participant(['first_name' => 'Jan', 'email' => 'jan@example.com']),
            course: new Course(['title' => 'Szkolenie']),
            hasPneduAccount: true,
            courseUrl: 'https://pnedu.pl/dashboard',
            certificateUrl: null,
            registerUrl: 'https://pnedu.pl/register',
            participantEmail: 'jan@example.com',
            hasVideos: true,
            hasMaterials: false,
            hasCertificate: false,
        );

        $this->assertSystemMailHeaders($mail);
        $this->assertNoLegacyDomains($mail->build()->render());
    }

    public function test_certificate_link_mails_use_system_mailer(): void
    {
        $participant = new Participant(['first_name' => 'Anna']);
        $course = new Course(['title' => 'Kurs']);

        $listMail = new CertificateLinkMail($participant, $course, 'https://pnedu.pl/certificates/token');
        $this->assertSystemMailHeaders($listMail);

        $singleMail = new CertificateSingleLinkMail($participant, $course, 'https://pnedu.pl/certificate/token/1');
        $this->assertSystemMailHeaders($singleMail);
    }

    public function test_data_completion_mail_uses_system_mailer_and_brand_in_template(): void
    {
        $token = new DataCompletionToken([
            'token' => 'test-token',
            'email' => 'jan@example.com',
        ]);

        $mail = new DataCompletionRequestMail(
            $token,
            Collection::make([new Course(['title' => 'Kurs testowy', 'start_date' => now()])]),
            'Jan Kowalski',
            false
        );

        $this->assertSystemMailHeaders($mail);

        $html = $mail->build()->render();
        $this->assertNoLegacyDomains($html);
        $this->assertStringContainsString('kontakt@pnedu.pl', $html);
        $this->assertStringContainsString('https://pnedu.pl', $html);
        $this->assertStringContainsString('www.pnedu.pl', $html);
    }

    public function test_pnedu_provision_notifications_use_system_mailer(): void
    {
        $existing = (new PneduFormOrderProvisionedExistingUser('Kurs', 'Prowadzący: Jan', 'Data: 01.01.2026'))
            ->toMail(new class
            {
                public function getEmailForPasswordReset(): string
                {
                    return 'jan@example.com';
                }
            });

        $this->assertInstanceOf(MailMessage::class, $existing);
        $this->assertSame('ses', $existing->mailer);
        $this->assertSame(['info@system.pnedu.pl', 'Platforma Nowoczesnej Edukacji'], $existing->from);
        $this->assertContains(['kontakt@pnedu.pl', 'Platforma Nowoczesnej Edukacji'], $existing->replyTo);

        $newUser = (new PneduFormOrderProvisionedNewUser('token', 'Kurs'))
            ->toMail(new class
            {
                public function getEmailForPasswordReset(): string
                {
                    return 'jan@example.com';
                }
            });

        $this->assertSame('ses', $newUser->mailer);
        $this->assertSame(['info@system.pnedu.pl', 'Platforma Nowoczesnej Edukacji'], $newUser->from);
        $this->assertContains(['kontakt@pnedu.pl', 'Platforma Nowoczesnej Edukacji'], $newUser->replyTo);
    }

    public function test_pnedu_frontend_reset_password_uses_system_mailer(): void
    {
        $notification = new PneduFrontendResetPassword('reset-token');
        $message = $notification->toMail(new class
        {
            public function getEmailForPasswordReset(): string
            {
                return 'jan@example.com';
            }
        });

        $this->assertSame('ses', $message->mailer);
        $this->assertSame(['info@system.pnedu.pl', 'Platforma Nowoczesnej Edukacji'], $message->from);
        $this->assertContains(['kontakt@pnedu.pl', 'Platforma Nowoczesnej Edukacji'], $message->replyTo);
    }
}
