<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dostęp do szkolenia</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 680px; margin: 0 auto; padding: 20px; }
        .content { background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
        .btn-link { display: inline-block; margin: 10px 8px 10px 0; padding: 12px 24px; background-color: #0d6efd; color: #fff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn-link-secondary { background-color: #198754; }
        .btn-link-outline { background-color: #fff; color: #0d6efd !important; border: 2px solid #0d6efd; }
        .btn-link:hover { background-color: #0b5ed7; color: #fff !important; }
        .btn-link-secondary:hover { background-color: #157347; }
        .meta { margin: 12px 0 0; padding: 12px; background: #fff; border: 1px solid #e9ecef; border-radius: 5px; }
        .meta p { margin: 0 0 6px; }
        .meta p:last-child { margin-bottom: 0; }
        .notice { margin: 14px 0; padding: 12px; background: #fff3cd; border: 1px solid #ffecb5; border-radius: 5px; font-size: 0.95em; }
        .wrap-link { overflow-wrap: anywhere; word-break: break-word; }
        .footer { margin-top: 20px; font-size: 0.9em; color: #6c757d; }
    </style>
</head>
<body>
    <div class="content">
        <p>Dzień dobry {{ $participantFirstName }},</p>

        @php
            $courseTitleQuoted = '„' . $courseTitle . '”';
        @endphp

        @if(!empty($courseDateLong))
            <p>Dziękujemy za udział w szkoleniu {{ $courseTitleQuoted }}, które odbyło się {{ $courseDateLong }}</p>
        @else
            <p>Dziękujemy za udział w szkoleniu {{ $courseTitleQuoted }}.</p>
        @endif

        @if($hasPneduAccount)
            <p>Na Twoim koncie pnedu.pl zostały udostępnione:</p>
        @else
            <p>Informujemy o dostępnych materiałach ze szkolenia:</p>
        @endif

        <div class="meta">
            @if($hasVideos)
                <p>- nagranie szkolenia{{ (!$hasPneduAccount && $needsAccountForRecordings) ? ' (wymaga konta na pnedu.pl)' : '' }}</p>
            @endif
            @if($hasMaterials)
                <p>- materiały szkoleniowe{{ (!$hasPneduAccount && $needsAccountForRecordings) ? ' (wymagają konta na pnedu.pl)' : '' }}</p>
            @endif
            @if($hasCertificate)
                <p>- zaświadczenie do pobrania{{ ! $hasPneduAccount ? ' (link poniżej – bez logowania)' : '' }}</p>
            @endif
        </div>

        @if($hasPneduAccount)
            <p>Zaloguj się na konto pnedu.pl (adres <strong>{{ $participantEmail }}</strong>), aby skorzystać z dostępu.</p>
        @elseif($needsAccountForRecordings)
            <div class="notice">
                <p style="margin: 0;">
                    Dostęp do nagrania oraz materiałów szkoleniowych wymaga założenia konta na pnedu.pl
                    na adres <strong>{{ $participantEmail }}</strong> i zalogowania się na nie.
                    Rejestrację wykonujesz samodzielnie – tylko wtedy, gdy chcesz skorzystać z tych materiałów.
                    Konto możesz w każdej chwili usunąć w panelu użytkownika na pnedu.pl.
                </p>
            </div>
        @endif

        @if(!empty($hasLimitedAccess) && !empty($accessExpiresAtFormatted) && empty($accessExpired))
            <p><strong>UWAGA!</strong><br>Dostęp do nagrania oraz materiałów wygaśnie: {{ $accessExpiresAtFormatted }}</p>
        @elseif(!empty($hasLimitedAccess) && !empty($accessExpiresAtFormatted) && !empty($accessExpired))
            <p><strong>UWAGA!</strong><br>Dostęp do nagrania oraz materiałów wygasł: {{ $accessExpiresAtFormatted }}</p>
        @endif

        @if(!empty($surveyLinks) && count($surveyLinks) > 0)
            <div class="meta" style="margin-top: 14px;">
                <p style="margin-bottom: 8px;">Pomóż nam doskonalić nasze szkolenia. Wypełnij krótką ankietę poszkoleniową — Twoja opinia pozwoli nam jeszcze lepiej odpowiadać na potrzeby dyrektorów i nauczycieli.</p>
                @foreach($surveyLinks as $sl)
                    <p style="margin: 0 0 6px;">
                        <a class="wrap-link" href="{{ $sl['url'] }}">{{ $sl['title'] }}</a>
                    </p>
                @endforeach
            </div>
        @endif

        <p>
            @if($hasPneduAccount && !empty($courseUrl))
                <a href="{{ $courseUrl }}" class="btn-link">Przejdź do szkolenia na pnedu.pl</a>
            @endif
            @if($hasCertificate && !empty($certificateUrl))
                <a href="{{ $certificateUrl }}" class="btn-link {{ $hasPneduAccount ? 'btn-link-secondary' : '' }}">Pobierz zaświadczenie</a>
            @endif
            @if($needsAccountForRecordings)
                <a href="{{ $registerUrl }}" class="btn-link btn-link-outline">Załóż konto na pnedu.pl</a>
            @endif
        </p>

        @if($hasPneduAccount && !empty($courseUrl))
            <p>Link do szkolenia (wymaga zalogowania):<br>
                <a class="wrap-link" href="{{ $courseUrl }}">{{ $courseUrl }}</a>
            </p>
        @endif

        @if($hasCertificate && !empty($certificateUrl))
            <p>Link do zaświadczenia (bez logowania, przypisany do adresu z tej wiadomości):<br>
                <a class="wrap-link" href="{{ $certificateUrl }}">{{ $certificateUrl }}</a>
            </p>
        @endif

        @if($needsAccountForRecordings)
            <p>Rejestracja konta (użyj adresu <strong>{{ $participantEmail }}</strong>):<br>
                <a class="wrap-link" href="{{ $registerUrl }}">{{ $registerUrl }}</a>
            </p>
            <p class="text-muted" style="font-size: 0.9em; color: #6c757d;">
                Po rejestracji i zalogowaniu nagranie oraz materiały będą dostępne w panelu „Moje szkolenia” na pnedu.pl.
            </p>
        @endif

        <div class="footer">
            <p>
                Z poważaniem,<br>
                Waldemar Grabowski<br>
                Akredytowany Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>
                "Platforma Nowoczesnej Edukacji"
            </p>
        </div>
    </div>
</body>
</html>
