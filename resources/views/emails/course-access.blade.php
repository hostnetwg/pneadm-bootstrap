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
        .btn-link:hover { background-color: #0b5ed7; }
        .btn-link-secondary:hover { background-color: #157347; }
        .meta { margin: 12px 0 0; padding: 12px; background: #fff; border: 1px solid #e9ecef; border-radius: 5px; }
        .meta p { margin: 0 0 6px; }
        .meta p:last-child { margin-bottom: 0; }
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
            <p>Dziękujemy za udział w szkoleniu {{ $courseTitleQuoted }}, które odbyło się {{ $courseDateLong }} Wszystkie materiały są już dla Ciebie dostępne online.</p>
        @else
            <p>Dziękujemy za udział w szkoleniu {{ $courseTitleQuoted }}. Wszystkie materiały są już dla Ciebie dostępne online.</p>
        @endif

        <p>Na Twoim koncie pnedu.pl zostały udostępnione:</p>

        <div class="meta">
            @if($hasVideos)
                <p>- nagranie szkolenia</p>
            @endif
            @if($hasMaterials)
                <p>- materiały szkoleniowe</p>
            @endif
            @if($hasCertificate)
                <p>- zaświadczenie do pobrania</p>
            @endif
        </div>

        @if($accountCreatedNow)
            <p>Aby skorzystać z dostępu, ustaw hasło i zaloguj się na konto.</p>
        @else
            <p>Zaloguj się na konto, aby skorzystać z dostępu.</p>
        @endif

        @if(!empty($hasLimitedAccess) && !empty($accessExpiresAtFormatted) && empty($accessExpired))
            <p><strong>UWAGA!</strong><br>Dostęp do nagrania oraz materiałów wygaśnie: {{ $accessExpiresAtFormatted }}</p>
        @elseif(!empty($hasLimitedAccess) && !empty($accessExpiresAtFormatted) && !empty($accessExpired))
            <p><strong>UWAGA!</strong><br>Dostęp do nagrania oraz materiałów wygasł: {{ $accessExpiresAtFormatted }}</p>
        @endif

        <p>
            <a href="{{ $courseUrl }}" class="btn-link">Przejdź do szkolenia na pnedu.pl</a>
            @if($accountCreatedNow && !empty($setPasswordUrl))
                <a href="{{ $setPasswordUrl }}" class="btn-link btn-link-secondary">Ustaw hasło</a>
            @endif
        </p>

        <p>Link do szkolenia (wymaga zalogowania):<br>
            <a class="wrap-link" href="{{ $courseUrl }}">{{ $courseUrl }}</a>
        </p>

        @if($accountCreatedNow && !empty($setPasswordUrl))
            <p>Link do ustawienia hasła:<br>
                <a class="wrap-link" href="{{ $setPasswordUrl }}">{{ $setPasswordUrl }}</a>
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
