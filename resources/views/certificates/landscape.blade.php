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
            padding: 20px;
            height: 100%;
        }
        @page {
            size: A4 landscape;
        }
        .certificate-title {
            font-size: 36px;
            font-weight: bold;
            color: #000000;
        }
        .course-title {
            word-break: keep-all;
            font-size: 30px;
            font-weight: bold;
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
            bottom: 10px;
            left: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="footer">
        <div style="text-align: center; margin-bottom: 15px;">
            @php
                $logoPath = 'certificates/logos/1759876024_logo-pne-czarne.png';
                if ($isPdfMode ?? false) {
                    // Dla PDF używamy base64 encoding
                    $logoFile = storage_path('app/public/' . $logoPath);
                    $logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($logoFile));
                } else {
                    // Dla HTML używamy asset()
                    $logoSrc = asset('storage/' . $logoPath);
                }
            @endphp
            <img src="{{ $logoSrc }}" alt="Logo" style="max-width: 120px; height: auto;">
        </div>
        Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji<br>ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>- AKREDYTACJA MAZOWIECKIEGO KURATORA OŚWIATY -
    </div>
    <h1 class="certificate-title">ZAŚWIADCZENIE O UKOŃCZENIU SZKOLENIA</h1>
    <p>ukończył/a szkolenie</p>
    <p>zorganizowane w dniu {{ \Carbon\Carbon::parse($course->start_date)->format('d.m.Y') }}r. w wymiarze {{ $durationMinutes }} minut, przez</p>

    <p class="bold">Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji</p>

    <h3>TEMAT SZKOLENIA</h3>
    <h2 class="course-title">{{ $course->title }}</h2>

    @php
        $description = trim($course->description ?? '');
        if (!empty($description)) {
            echo '<p>Zakres:</p>';
            if (preg_match('/^\\d+\\.\\s*/m', $description)) {
                // To jest lista numerowana - formatuj jako <ol>
                $items = preg_split('/\\n(?=\\d+\\.)/', $description);
                echo '<ol style="text-align: left; margin-left: 0px;">';
                foreach ($items as $item) {
                    $cleanItem = preg_replace('/^\\d+\\.\\s*/', '', trim($item));
                    if ($cleanItem) {
                        echo '<li style="text-align: left; margin-bottom: 5px;">' . htmlspecialchars($cleanItem) . '</li>';
                    }
                }
                echo '</ol>';
            } else {
                // To jest zwykły tekst - jako akapit wyrównany do lewej
                echo '<p style="text-align: left;">' . htmlspecialchars($description) . '</p>';
            }
        }
    @endphp

    <p>Pan/i</p>
    <h2>{{ $participant->first_name }} {{ $participant->last_name }}</h2>

    @if (!empty($participant->birth_date) && !empty($participant->birth_place))
        <p>urodzony/a: {{ \Carbon\Carbon::parse($participant->birth_date)->format('d.m.Y') }}r. w miejscowości {{ $participant->birth_place }}</p>
    @else
        <p>&nbsp;</p>
    @endif

    <div class="signature">
        <p>prowadzący/a:<br>
        <span class="bold">{{ $instructor->first_name }} {{ $instructor->last_name }}</span></p>
    </div>

    <div style="text-align: left;">
        <p>Data, {{ \Carbon\Carbon::parse($course->end_date)->format('d.m.Y') }}r.<br>
        Nr rejestru: {{ $certificateNumber }}</p>
    </div>

</body>
</html>
