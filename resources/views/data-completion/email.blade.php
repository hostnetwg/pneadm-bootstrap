<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prośba o uzupełnienie danych</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #0d6efd;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 20px;
            border: 1px solid #dee2e6;
        }
        .courses-list {
            background-color: white;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            border-left: 4px solid #0d6efd;
        }
        .course-item {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .course-item:last-child {
            border-bottom: none;
        }
        .course-title {
            font-weight: bold;
            color: #0d6efd;
        }
        .course-details {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }
        .button {
            display: inline-block;
            background-color: #0d6efd;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        .footer {
            text-align: center;
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Prośba o uzupełnienie danych</h1>
        <div style="font-size: 0.85em; margin-top: 10px; opacity: 0.9;">
            Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>
            "<em>Platforma Nowoczesnej Edukacji</em>"
        </div>
    </div>
    
    <div class="content">
        @if($isTestMode ?? false)
            <div style="background-color: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                <strong>⚠️ TO JEST TESTOWY EMAIL</strong><br>
                Ten email został wysłany w trybie testowym na adres waldemar.grabowski@hostnet.pl.<br>
                Oryginalny adres odbiorcy: {{ $token->email }}
            </div>
        @endif
        
        <p>Witaj {{ $participantName }},</p>
        
        <p>
            Zgodnie z przepisami dotyczącymi prowadzenia rejestru wydanych zaświadczeń, 
            potrzebujemy uzupełnienia Twoich danych osobowych.
        </p>
        
        <p>
            Brakuje nam następujących informacji:
        </p>
        <ul>
            <li>Data urodzenia</li>
            <li>Miejsce urodzenia</li>
        </ul>
        
        <p>
            Aby uzupełnić dane, kliknij w poniższy przycisk:
        </p>
        
        <div style="text-align: center; margin-bottom: 20px;">
            <a href="{{ $formUrl }}" class="button">Uzupełnij dane</a>
        </div>
        
        <p style="background-color: #fff3cd; padding: 12px; border-left: 4px solid #ffc107; margin: 20px 0;">
            <strong>Ważne:</strong> Po uzupełnieniu danych, zostaną one automatycznie zaktualizowane 
            we wszystkich zaświadczeniach dotyczących szkoleń wymienionych poniżej.
        </p>
        
        <p>
            Poniżej znajduje się lista wszystkich szkoleń, w których brałeś/aś udział:
        </p>
        
        <div class="courses-list">
            @foreach($courses as $course)
                <div class="course-item">
                    <div class="course-title">{{ str_replace('&nbsp;', ' ', $course->title) }}</div>
                    <div class="course-details">
                        @if($course->start_date)
                            Data: {{ \Carbon\Carbon::parse($course->start_date)->format('d.m.Y') }}
                        @endif
                        @if($course->instructor)
                            @if($course->start_date) | @endif
                            Prowadzący: {{ $course->instructor->full_name ?? ($course->instructor->first_name . ' ' . $course->instructor->last_name) }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        
        <p>
            Jeśli masz pytania, prosimy o kontakt: <a href="mailto:kontakt@nowoczesna-edukacja.pl" style="color: #0d6efd;">kontakt@nowoczesna-edukacja.pl</a>
        </p>
        
    </div>
    
    <div class="footer">
        <p>
            Z wyrazami szacunku,<br>
            Waldemar Grabowski<br>
            <b>Niepubliczny Ośrodek Doskonalenia Nauczycieli</b><br>
            "<b>Platforma Nowoczesnej Edukacji</b>"<br>
            <img src="https://pnedu.pl/grafika/NODN Platforma Nowoczesnej Edukacji - logo.png" title="PNE- LOGO" style="max-width: 200px; height: auto; margin: 10px 0;"><br>
            <a href="https://nowoczesna-edukacja.pl" style="color: #0d6efd;">nowoczesna-edukacja.pl</a>
        </p>
        <p style="margin-top: 20px; font-size: 0.85em;">
            Ten email został wysłany automatycznie. Prosimy nie odpowiadać na tę wiadomość.<br>
            Link do formularza jest ważny przez 30 dni.
        </p>
    </div>
</body>
</html>

