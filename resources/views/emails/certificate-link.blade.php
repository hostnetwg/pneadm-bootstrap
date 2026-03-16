<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Link do zaświadczeń</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .content { background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
        .btn-link { display: inline-block; margin: 15px 0; padding: 12px 24px; background-color: #0d6efd; color: #fff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn-link:hover { background-color: #0b5ed7; }
        .footer { margin-top: 20px; font-size: 0.9em; color: #6c757d; }
    </style>
</head>
<body>
    <div class="content">
        <p>Dzień dobry {{ $participantFirstName }},</p>
        <p>Wysyłamy link do pobrania zaświadczeń ze szkoleń, w których brałeś/aś udział.</p>
        <p>Kliknij w poniższy link, aby przejść do listy swoich zaświadczeń i pobrać je w formacie PDF:</p>
        <p>
            <a href="{{ $certificatesUrl }}" class="btn-link">Pobierz zaświadczenia</a>
        </p>
        <p>Link (jeśli przycisk nie działa):<br>
            <a href="{{ $certificatesUrl }}">{{ $certificatesUrl }}</a>
        </p>
        <p>Link jest przypisany do Twojego adresu e-mail i umożliwia dostęp do zaświadczeń z wszystkich szkoleń, w których uczestniczyłeś/aś.</p>
        <div class="footer">
            <p>Z poważaniem,<br>{{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
