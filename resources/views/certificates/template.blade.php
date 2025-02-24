<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zaświadczenie</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            text-align: center;
            position: relative;
            margin: 0;
            padding: 0;
            height: 100%;
        }
        .certificate-title {        
            font-size: 38px;
            font-weight: bold;
        }
        .course-title {
            word-break: keep-all;         
            font-size: 32px;
            font-weight: bold;
        }        
        .certificate-section {
            margin: 20px 0;
        }
        .bold {
            font-weight: bold;
        }
        .signature {
            margin-top: 50px;
            text-align: right;
        }
        .footer {
            font-size: 10px;
            text-align: center;
            position: absolute;
            bottom: 10px; /* Odległość od dolnej krawędzi */
            left: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <h1 class="certificate-title">ZAŚWIADCZENIE</h1>
    
    <p>Pan/i</p>
    <h2>{{ $participant->first_name }} {{ $participant->last_name }}</h2>

    @if (!empty($participant->birth_date) && !empty($participant->birth_place))
        <p>urodzony/a: {{ \Carbon\Carbon::parse($participant->birth_date)->format('d.m.Y') }}r. w miejscowości {{ $participant->birth_place }}</p>
    @else
        <p>&nbsp;</p> {{-- Puste miejsce jeśli brak danych --}}
    @endif

    <p>ukończył/a szkolenie</p>
    <p>zorganizowane w dniu {{ \Carbon\Carbon::parse($course->start_date)->format('d.m.Y') }}r. w wymiarze {{ $durationMinutes }} minut, przez</p>

    <p class="bold">Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji</p>

    <h3>TEMAT SZKOLENIA</h3>
    <h2 class="course-title">{{ $course->title }}</h2>

    <p>Zakres:</p>
    <p>{{ $course->description }}</p>

    <div class="signature">
        <p>prowadzący/a:<br>
        <span class="bold">{{ $instructor->first_name }} {{ $instructor->last_name }}</span></p>
    </div>

    <div style="text-align: left;">
        {{-- <p>Data, {{ now()->format('d.m.Y') }}r.<br> --}}
        <p>Data, {{ \Carbon\Carbon::parse($course->end_date)->format('d.m.Y') }}r.<br>
        Nr rejestru: {{ $certificateNumber }}</p>
    </div>

    <div class="footer">
        Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji<br>
        ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>
        - AKREDYTACJA MAZOWIECKIEGO KURATORA OŚWIATY -
    </div>
</body>
</html>
