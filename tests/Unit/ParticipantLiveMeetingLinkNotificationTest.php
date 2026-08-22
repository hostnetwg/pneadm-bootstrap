<?php

namespace Tests\Unit;

use App\Notifications\ParticipantLiveMeetingLinkNotification;
use App\Support\PneduProvisionLiveAccessContext;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class ParticipantLiveMeetingLinkNotificationTest extends TestCase
{
    public function test_mail_contains_join_url_token_and_password(): void
    {
        $live = new PneduProvisionLiveAccessContext(
            showLiveSection: true,
            platformLabel: 'ClickMeeting',
            joinUrl: 'https://pnedu.clickmeeting.com/wydarzenie/TOK99',
            token: 'TOK99',
            password: 'haslo123',
            showSpamNote: true,
            showPostEventSection: false,
        );

        $notification = new ParticipantLiveMeetingLinkNotification(
            courseTitle: 'Szkolenie testowe Live',
            participantFirstName: 'Anna',
            instructorLine: 'Prowadzący: Jan Kowalski',
            scheduleLine: 'Termin: 20.07.2026 10:00–12:00',
            liveAccess: $live,
            dashboardSzkoleniaUrl: 'http://edu.localhost:8081/dashboard/szkolenia',
        );

        $mail = $notification->toMail(new AnonymousNotifiable);

        $this->assertSame('Spotkanie na żywo — Szkolenie testowe Live', $mail->subject);
        $rendered = implode("\n", array_map(
            fn ($line) => is_object($line) && method_exists($line, '__toString') ? (string) $line : (string) $line,
            $mail->introLines
        ));

        $this->assertStringContainsString('https://pnedu.clickmeeting.com/wydarzenie/TOK99', $rendered);
        $this->assertStringContainsString('TOK99', $rendered);
        $this->assertStringContainsString('haslo123', $rendered);
        $this->assertStringContainsString('przypisany do Twojego adresu e-mail', $rendered);
        $this->assertStringContainsString('Twoje szkolenia', $rendered);
        $this->assertStringContainsString('SPAM', $rendered);

        $this->assertNotEmpty($mail->actionUrl);
        $this->assertSame('https://pnedu.clickmeeting.com/wydarzenie/TOK99', $mail->actionUrl);
        $this->assertSame('Dołącz do spotkania na żywo', $mail->actionText);
    }

    public function test_mail_contains_embedded_link_and_direct_clickmeeting_fallback(): void
    {
        $live = new PneduProvisionLiveAccessContext(
            showLiveSection: true,
            platformLabel: 'pnedu.pl / ClickMeeting',
            joinUrl: 'https://pnedu.pl/dashboard/szkolenia/123/transmisja?fullscreen=1',
            directJoinUrl: 'https://pnedu.clickmeeting.com/wydarzenie/TOK99',
            token: 'TOK99',
            showSpamNote: true,
            showPostEventSection: false,
            usesEmbeddedJoin: true,
        );

        $notification = new ParticipantLiveMeetingLinkNotification(
            courseTitle: 'Szkolenie testowe Live',
            participantFirstName: 'Anna',
            instructorLine: null,
            scheduleLine: null,
            liveAccess: $live,
            dashboardSzkoleniaUrl: 'https://pnedu.pl/dashboard/szkolenia',
        );

        $mail = $notification->toMail(new AnonymousNotifiable);
        $rendered = implode("\n", array_map(
            fn ($line) => is_object($line) && method_exists($line, '__toString') ? (string) $line : (string) $line,
            $mail->introLines
        ));

        $this->assertStringContainsString('Link do pokoju osadzonego w pnedu.pl', $rendered);
        $this->assertStringContainsString('https://pnedu.pl/dashboard/szkolenia/123/transmisja?fullscreen=1', $rendered);
        $this->assertStringContainsString('Jeśli wejście przez pnedu.pl nie zadziała, skorzystaj z bezpośredniego linku do ClickMeeting', $rendered);
        $this->assertStringContainsString('https://pnedu.clickmeeting.com/wydarzenie/TOK99', $rendered);
        $this->assertStringNotContainsString('Token dostępu', $rendered);
        $this->assertSame('https://pnedu.pl/dashboard/szkolenia/123/transmisja?fullscreen=1', $mail->actionUrl);
    }
}
