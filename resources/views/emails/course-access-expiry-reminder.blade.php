<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Przypomnienie o wygaśnięciu dostępu</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 680px; margin: 0 auto; padding: 20px; }
        .content { background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
        .btn-link { display: inline-block; margin: 10px 8px 10px 0; padding: 12px 24px; background-color: #0d6efd; color: #fff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn-link-secondary { background-color: #198754; }
        .btn-link-outline { background-color: #fff; color: #0d6efd !important; border: 2px solid #0d6efd; }
        .notice-warn { margin: 14px 0; padding: 12px; background: #fff3cd; border: 1px solid #ffecb5; border-radius: 5px; }
        .notice-ok { margin: 14px 0; padding: 12px; background: #d1e7dd; border: 1px solid #badbcc; border-radius: 5px; }
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
            <p>To przypomnienie dotyczy szkolenia {{ $courseTitleQuoted }}, w którym brałeś/aś udział {{ $courseDateLong }}</p>
        @else
            <p>To przypomnienie dotyczy szkolenia {{ $courseTitleQuoted }}.</p>
        @endif

        <div class="notice-warn">
            <p style="margin: 0 0 8px;"><strong>UWAGA — ograniczony czas dostępu</strong></p>
            <p style="margin: 0;">
                {{ ucfirst($timingLabel) }} (<strong>{{ $accessExpiresAtFormatted }}</strong>) wygasa dostęp do
                @if($hasVideos && $hasMaterials)
                    nagrania szkolenia oraz materiałów szkoleniowych
                @elseif($hasVideos)
                    nagrania szkolenia
                @else
                    materiałów szkoleniowych
                @endif
                na platformie pnedu.pl.
                Po tym terminie nie będzie możliwe odtworzenie nagrania ani pobranie materiałów z panelu szkolenia.
            </p>
        </div>

        @if($hasCertificate)
            <div class="notice-ok">
                <p style="margin: 0;">
                    <strong>Zaświadczenie bez terminu ważności:</strong> link do pobrania zaświadczenia (PDF)
                    <strong>nie wygasa</strong> — możesz je pobrać także po zakończeniu dostępu do nagrania i materiałów.
                </p>
            </div>
        @endif

        @if($needsAccountForRecordings)
            <p class="small text-muted" style="color: #6c757d;">
                Dostęp do nagrania i materiałów wymaga konta na pnedu.pl (adres <strong>{{ $participantEmail }}</strong>).
            </p>
        @endif

        <p>
            @if($hasPneduAccount && !empty($courseUrl))
                <a href="{{ $courseUrl }}" class="btn-link">Przejdź do szkolenia na pnedu.pl</a>
            @endif
            @if($hasCertificate && !empty($certificateUrl))
                <a href="{{ $certificateUrl }}" class="btn-link btn-link-secondary">Pobierz zaświadczenie</a>
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
            <p>Link do zaświadczenia (bez logowania):<br>
                <a class="wrap-link" href="{{ $certificateUrl }}">{{ $certificateUrl }}</a>
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
