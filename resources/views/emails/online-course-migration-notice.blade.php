<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Przeniesienie kursów na pnedu.pl</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 680px; margin: 0 auto; padding: 20px; }
        .content { background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
        .btn-link { display: inline-block; margin: 10px 8px 10px 0; padding: 12px 24px; background-color: #0d6efd; color: #fff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn-link-outline { background-color: #fff; color: #0d6efd !important; border: 2px solid #0d6efd; }
        .meta { margin: 12px 0 0; padding: 12px; background: #fff; border: 1px solid #e9ecef; border-radius: 5px; }
        .notice { margin: 14px 0; padding: 12px; background: #fff3cd; border: 1px solid #ffecb5; border-radius: 5px; }
        .wrap-link { overflow-wrap: anywhere; word-break: break-word; }
        .footer { margin-top: 20px; font-size: 0.9em; color: #6c757d; }
    </style>
</head>
<body>
    <div class="content">
        <p>Dzień dobry {{ $participantFirstName }},</p>

        <p>
            <strong>Przenosimy kursy online</strong>
            ze starej platformy <strong>nowoczesna-edukacja.pl</strong>
            na nową platformę <strong>pnedu.pl</strong>.
            Na dotychczasowej platformie te kursy <strong>nie będą już dostępne</strong>.
        </p>

        <p>Twoje kursy na pnedu.pl:</p>
        <ul>
            @foreach($courseTitles as $title)
                <li>{{ $title }}</li>
            @endforeach
        </ul>

        <div class="meta">
            <p>Dostęp jest przypisany do adresu e-mail: <strong>{{ $participantEmail }}</strong></p>
            <p>Po zalogowaniu znajdziesz je w panelu <strong>Kursy online</strong>.</p>
            <p>Użyj <strong>tego samego adresu e-mail</strong>, na który kupowałeś dostęp na starej platformie.</p>
        </div>

        @if($hasPneduAccount)
            <p>
                Na adres <strong>{{ $participantEmail }}</strong> jest już konto na pnedu.pl.
                Zaloguj się, a kursy znajdziesz w panelu <strong>Kursy online</strong>.
            </p>
            <p>
                <a href="{{ $loginUrl }}" class="btn-link">Zaloguj się na pnedu.pl</a>
                <a href="{{ $coursesUrl }}" class="btn-link">Przejdź do kursów</a>
            </p>
            <p>
                Jeśli nie pamiętasz hasła, ustaw nowe na stronie resetu hasła
                (wpisz ten sam adres e-mail: <strong>{{ $participantEmail }}</strong>):
            </p>
            <p>
                <a href="{{ $forgotPasswordUrl }}" class="btn-link btn-link-outline">Nie pamiętam hasła</a>
            </p>
        @else
            <div class="notice">
                <p style="margin: 0;">
                    Na pnedu.pl <strong>nie ma jeszcze konta</strong> z adresem <strong>{{ $participantEmail }}</strong>.
                    Żeby otworzyć kursy, załóż konto <strong>na ten sam adres e-mail</strong> i zaloguj się.
                    Ten list konta nie zakłada. Konto możesz później usunąć w panelu użytkownika.
                </p>
            </div>
            <p>
                <a href="{{ $registerUrl }}" class="btn-link">Załóż konto na pnedu.pl</a>
            </p>
            <p>Rejestracja (użyj adresu <strong>{{ $participantEmail }}</strong>):<br>
                <a class="wrap-link" href="{{ $registerUrl }}">{{ $registerUrl }}</a>
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
