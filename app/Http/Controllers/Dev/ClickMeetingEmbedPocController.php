<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Services\ClickMeetingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClickMeetingEmbedPocController extends Controller
{
    public function show(Request $request, ClickMeetingService $clickMeetingService): View
    {
        $this->assertPocAccess($request);

        $roomId = trim((string) ($request->query('room_id') ?: config('services.clickmeeting.poc_room_id', '')));
        $email = strtolower(trim((string) ($request->query('email') ?: config('services.clickmeeting.poc_email', ''))));
        $nickname = trim((string) $request->query('nickname', 'Dev Tester'));
        $variant = (string) $request->query('variant', 'iframe_autologin');
        $tokenOverride = trim((string) $request->query('token', ''));
        $secret = (string) config('services.clickmeeting.poc_secret');

        $errors = [];
        $conference = null;
        $accessType = null;
        $roomUrl = null;
        $embedScriptUrl = null;
        $roomPin = null;
        $token = null;
        $pinEmbedPlainSrc = null;
        $pinEmbedAutologinSrc = null;
        $roomAutologinUrl = null;
        $roomTokenUrl = null;

        if ($roomId === '') {
            $errors[] = 'Ustaw CLICKMEETING_POC_ROOM_ID w .env lub parametr room_id w URL.';
        }

        if ($email === '' || ! str_contains($email, '@')) {
            $errors[] = 'Ustaw CLICKMEETING_POC_EMAIL w .env lub parametr email w URL.';
        }

        if ($errors === [] && $roomId !== '') {
            $conferenceResult = $clickMeetingService->getConference($roomId);
            if (! ($conferenceResult['success'] ?? false)) {
                $errors[] = (string) ($conferenceResult['error'] ?? 'Nie udało się pobrać wydarzenia z ClickMeeting.');
            } else {
                $conference = $conferenceResult['conference'] ?? [];
                $accessType = isset($conferenceResult['access_type'])
                    ? (int) $conferenceResult['access_type']
                    : null;
                $roomUrl = $clickMeetingService->extractRoomUrl($conference);
                $embedScriptUrl = $clickMeetingService->extractEmbedRoomUrl($conference);
                $roomPin = $clickMeetingService->extractRoomPin($conference);

                if ($embedScriptUrl === null) {
                    $errors[] = 'API nie zwróciło embed_room_url — sprawdź typ wydarzenia (paid event blokuje embed).';
                }

                if ($roomPin === null || $roomUrl === null) {
                    $errors[] = 'Brak room_pin lub room_url — nie da się zbudować URL iframe.';
                } else {
                    $pinEmbedPlainSrc = $clickMeetingService->buildPinEmbedUrl($roomUrl, $roomPin);
                }

                if ($accessType === ClickMeetingService::ACCESS_TYPE_TOKEN) {
                    if ($tokenOverride !== '') {
                        $token = $tokenOverride;
                    } else {
                        $tokenResult = $clickMeetingService->getAccessTokenForEmail($roomId, $email);
                        if ($tokenResult['success'] ?? false) {
                            $token = trim((string) ($tokenResult['token'] ?? ''));
                        } else {
                            $errors[] = 'Wydarzenie wymaga tokenu, ale nie udało się go pobrać: '
                                .($tokenResult['error'] ?? 'nieznany błąd')
                                .'. Podaj token w URL (?token=...) lub zarejestruj e-mail w CM.';
                        }
                    }
                }

                if ($roomUrl !== null) {
                    $roomTokenUrl = $clickMeetingService->buildJoinUrl($roomUrl, $token);
                }

                $hashResult = $clickMeetingService->generateAutologinHash(
                    $roomId,
                    $email,
                    $nickname,
                    'listener',
                    $token
                );

                if ($hashResult['success'] ?? false) {
                    $autologinHash = (string) ($hashResult['autologin_hash'] ?? '');
                    if ($roomUrl !== null) {
                        $roomAutologinUrl = $clickMeetingService->buildAutologinUrl($roomUrl, $autologinHash);
                    }
                    if ($pinEmbedPlainSrc !== null) {
                        $pinEmbedAutologinSrc = $clickMeetingService->buildAutologinUrl($pinEmbedPlainSrc, $autologinHash);
                    }
                } else {
                    $errors[] = 'Auto-login hash: '.($hashResult['error'] ?? 'nieznany błąd');
                }
            }
        }

        $activeIframeSrc = match ($variant) {
            'iframe_plain' => $pinEmbedPlainSrc,
            'official_script' => null,
            default => $pinEmbedAutologinSrc ?? $pinEmbedPlainSrc,
        };

        $useOfficialScript = $variant === 'official_script';

        return view('dev.clickmeeting-embed-poc', [
            'secret' => $secret,
            'roomId' => $roomId,
            'email' => $email,
            'nickname' => $nickname,
            'variant' => $variant,
            'tokenMasked' => $this->maskToken($token),
            'accessType' => $accessType,
            'accessTypeLabel' => $this->accessTypeLabel($accessType),
            'conferenceName' => trim((string) ($conference['name'] ?? '')),
            'roomPin' => $roomPin,
            'errors' => $errors,
            'embedScriptUrl' => $embedScriptUrl,
            'pinEmbedPlainSrc' => $pinEmbedPlainSrc,
            'pinEmbedAutologinSrc' => $pinEmbedAutologinSrc,
            'roomAutologinUrl' => $roomAutologinUrl,
            'roomTokenUrl' => $roomTokenUrl,
            'activeIframeSrc' => $activeIframeSrc,
            'useOfficialScript' => $useOfficialScript,
        ]);
    }

    private function assertPocAccess(Request $request): void
    {
        if (! app()->environment('local')) {
            abort(404);
        }

        $secret = (string) config('services.clickmeeting.poc_secret', '');
        $provided = (string) $request->query('key', '');

        if ($secret === '' || ! hash_equals($secret, $provided)) {
            abort(404);
        }
    }

    private function maskToken(?string $token): ?string
    {
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }

        if (strlen($token) <= 2) {
            return str_repeat('*', strlen($token));
        }

        return str_repeat('*', max(0, strlen($token) - 2)).substr($token, -2);
    }

    private function accessTypeLabel(?int $accessType): string
    {
        return match ($accessType) {
            ClickMeetingService::ACCESS_TYPE_PASSWORD => 'Hasło (2)',
            ClickMeetingService::ACCESS_TYPE_TOKEN => 'Token (3)',
            1 => 'Otwarty (1)',
            null => '—',
            default => (string) $accessType,
        };
    }
}
