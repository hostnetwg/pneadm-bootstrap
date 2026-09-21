<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseOnlineDetails;
use Illuminate\Support\Str;

/**
 * Sekretny link osadzonego live bez konta pnedu — tylko szkolenia zamknięte + CM „Dla wszystkich”.
 * Pokazujemy go w ADM (karta + panel live). Maila do dyrektora jeszcze nie generujemy ani nie wysyłamy.
 */
class GuestLiveLinkService
{
    public function isEligible(Course $course): bool
    {
        if (($course->category ?? '') !== 'closed') {
            return false;
        }
        if (($course->type ?? '') !== 'online') {
            return false;
        }

        $course->loadMissing('onlineDetails');
        $details = $course->onlineDetails;
        if (! $details instanceof CourseOnlineDetails) {
            return false;
        }
        if (! (bool) $details->embed_on_pnedu) {
            return false;
        }

        $platform = strtolower(trim((string) ($details->platform ?? '')));
        $eventId = trim((string) ($details->clickmeeting_event_id ?? ''));

        return $platform === 'clickmeeting' && $eventId !== '';
    }

    public function ensureToken(Course $course): ?string
    {
        $course->loadMissing('onlineDetails');
        $details = $course->onlineDetails;
        if (! $details instanceof CourseOnlineDetails) {
            return null;
        }

        $existing = trim((string) ($details->guest_live_token ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        if (! $this->isEligible($course)) {
            return null;
        }

        $details->guest_live_token = Str::random(64);
        $details->save();

        return $details->guest_live_token;
    }

    public function publicUrl(?string $token): ?string
    {
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }

        $base = rtrim((string) config('services.pnedu_frontend_url', ''), '/');
        if ($base === '') {
            return null;
        }

        return $base.'/live/'.$token;
    }

    /**
     * @return array{eligible: bool, url: ?string, hint: string}
     */
    public function panelState(Course $course): array
    {
        $course->loadMissing('onlineDetails');
        $eligible = $this->isEligible($course);
        $token = $eligible ? $this->ensureToken($course) : trim((string) ($course->onlineDetails?->guest_live_token ?? ''));
        $url = $eligible ? $this->publicUrl($token) : null;

        return [
            'eligible' => $eligible,
            'url' => $url,
            'hint' => $this->hint($course, $eligible, $url),
        ];
    }

    private function hint(Course $course, bool $eligible, ?string $url): string
    {
        if ($eligible && $url) {
            return 'Skopiuj i wyślij dyrektorowi z własnej skrzynki. Na wejściu nauczyciel podaje imię, nazwisko i e-mail. Nie publikujemy tego na pnedu.pl. Maila z ADM jeszcze nie wysyłamy.';
        }
        if ($eligible && $url === null) {
            return 'Brak adresu pnedu.pl w konfiguracji panelu (PNEDU_FRONTEND_URL) — nie da się złożyć linku.';
        }
        if (($course->category ?? '') !== 'closed') {
            return 'Link bez logowania jest tylko przy szkoleniu zamkniętym.';
        }
        if (! (bool) ($course->onlineDetails?->embed_on_pnedu)) {
            return 'Włącz osadzony pokój na pnedu.pl, wtedy pojawi się link dla gości.';
        }

        return 'Link bez logowania wymaga ClickMeeting i ID wydarzenia.';
    }
}
