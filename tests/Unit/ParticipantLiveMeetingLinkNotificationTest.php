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
            participantEmail: 'anna@example.com',
            participantFirstName: 'Anna',
            instructorLine: 'Prowadzący: Jan Kowalski',
            scheduleLine: 'Data rozpoczęcia: 20.07.2026 10:00 (2 godz.)',
            liveAccess: $live,
            dashboardSzkoleniaUrl: 'http://edu.localhost:8081/dashboard/szkolenia',
        );

        $mail = $notification->toMail(new AnonymousNotifiable);

        $this->assertSame('Dostęp do szkolenia: Szkolenie testowe Live - spotkanie na żywo.', $mail->subject);
        $intro = implode("\n", array_map(
            fn ($line) => is_object($line) && method_exists($line, '__toString') ? (string) $line : (string) $line,
            $mail->introLines
        ));
        $outro = implode("\n", array_map(
            fn ($line) => is_object($line) && method_exists($line, '__toString') ? (string) $line : (string) $line,
            $mail->outroLines
        ));
        $body = $intro."\n".$outro;

        $this->assertStringContainsString('https://pnedu.clickmeeting.com/wydarzenie/TOK99', $body);
        $this->assertStringContainsString('TOK99', $body);
        $this->assertStringContainsString('haslo123', $body);
        $this->assertStringContainsString('jednorazowy', $body);
        $this->assertStringContainsString('Twoje szkolenia', $body);
        $this->assertStringContainsString('SPAM', $body);

        $this->assertSame('Dołącz do spotkania na żywo', $mail->actionText);
        $this->assertSame('https://pnedu.clickmeeting.com/wydarzenie/TOK99', $mail->actionUrl);
    }

    public function test_mail_contains_embedded_link_login_action_and_direct_clickmeeting_fallback(): void
    {
        config(['services.pnedu_frontend_url' => 'https://pnedu.pl']);

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
            participantEmail: 'anna@example.com',
            participantFirstName: 'Anna',
            instructorLine: null,
            scheduleLine: null,
            liveAccess: $live,
            dashboardSzkoleniaUrl: 'https://pnedu.pl/dashboard/szkolenia',
        );

        $mail = $notification->toMail(new AnonymousNotifiable);
        $intro = implode("\n", array_map(
            fn ($line) => is_object($line) && method_exists($line, '__toString') ? (string) $line : (string) $line,
            $mail->introLines
        ));
        $outro = implode("\n", array_map(
            fn ($line) => is_object($line) && method_exists($line, '__toString') ? (string) $line : (string) $line,
            $mail->outroLines
        ));

        $this->assertStringContainsString('Najwygodniej wejść przez pnedu.pl', $intro);
        $this->assertStringContainsString('Link do pokoju osadzonego w pnedu.pl', $outro);
        $this->assertStringContainsString('https://pnedu.pl/dashboard/szkolenia/123/transmisja?fullscreen=1', $outro);
        $this->assertStringContainsString('Jeśli wejście przez pnedu.pl nie zadziała, skorzystaj z bezpośredniego linku do ClickMeeting', $outro);
        $this->assertStringContainsString('https://pnedu.clickmeeting.com/wydarzenie/TOK99', $outro);
        $this->assertStringNotContainsString('Token dostępu', $outro);
        $this->assertStringNotContainsString('/transmisja?fullscreen=1', $intro);

        $this->assertSame('Zaloguj się na pnedu.pl', $mail->actionText);
        $this->assertStringContainsString('email=anna%40example.com', (string) $mail->actionUrl);
    }
}
