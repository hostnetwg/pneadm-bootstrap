<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appLabel }} v {{ $version }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .content { background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
        .btn-link { display: inline-block; margin: 15px 0; padding: 12px 24px; background-color: #0d6efd; color: #fff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .wrap-link { overflow-wrap: anywhere; word-break: break-word; }
        .footer { margin-top: 20px; font-size: 0.9em; color: #6c757d; }
    </style>
</head>
<body>
    <div class="content">
        <p>Dzień dobry{{ $recipientName !== '' ? ' '.$recipientName : '' }},</p>

        <p>
            W panelu ADM jest nowa wersja <strong>{{ $appLabel }} v {{ $version }}</strong>@if($date)
                ({{ $date }})
            @endif.
        </p>

        @if(count($bullets) > 0)
            <p>Najważniejsze zmiany:</p>
            <ul>
                @foreach($bullets as $bullet)
                    <li>{{ $bullet }}</li>
                @endforeach
            </ul>
        @endif

        <p>
            <a href="{{ $changelogUrl }}" class="btn-link">Otwórz historię wersji</a>
        </p>

        <p>Link (jeśli przycisk nie działa):<br>
            <a class="wrap-link" href="{{ $changelogUrl }}">{{ $changelogUrl }}</a>
        </p>

        <div class="footer">
            <p>To nie jest hotfix — mail idzie tylko przy nowym numerze wersji, po decyzji Waldemara.</p>
        </div>
    </div>
</body>
</html>
