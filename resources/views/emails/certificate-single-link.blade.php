<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zaświadczenie</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .content { background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
        .btn-link { display: inline-block; margin: 15px 0; padding: 12px 24px; background-color: #0d6efd; color: #fff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn-link:hover { background-color: #0b5ed7; }
        .wrap-link { overflow-wrap: anywhere; word-break: break-word; }
        .meta { margin: 12px 0 0; padding: 12px; background: #fff; border: 1px solid #e9ecef; border-radius: 5px; }
        .meta p { margin: 0 0 6px; }
        .meta p:last-child { margin-bottom: 0; }
        .footer { margin-top: 20px; font-size: 0.9em; color: #6c757d; }
    </style>
</head>
<body>
    <div class="content">
        <p>Dzień dobry {{ $participantFirstName }},</p>

        <p>Wysyłamy link do pobrania zaświadczenia (PDF) dla poniższego szkolenia:</p>

        <div class="meta">
            <p><strong>Szkolenie:</strong> {{ $courseTitle }}</p>
            @if(!empty($trainerName))
                <p><strong>Prowadzący:</strong> {{ $trainerName }}</p>
            @endif
        </div>

        <p>
            <a href="{{ $certificateUrl }}" class="btn-link">Pobierz zaświadczenie</a>
        </p>

        <p>Link (jeśli przycisk nie działa):<br>
            <a class="wrap-link" href="{{ $certificateUrl }}">{{ $certificateUrl }}</a>
        </p>

        <div class="footer">
            <p>Z poważaniem,<br>{{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>

