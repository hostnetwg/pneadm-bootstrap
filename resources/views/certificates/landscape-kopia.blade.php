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
            padding: 10px 20px;
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
        h3 {
            margin-bottom: 5px;
        }
        h2 {
            margin-top: 5px;
        }
        .course-title {
            word-break: normal;
            white-space: normal;
            hyphens: none;
            font-size: 30px;
            font-weight: bold;
            line-height: 1.1;
            margin-left: 45px;
            margin-right: 45px;
        }
        .bold {
            font-weight: bold;
        }
        .date-section {
            position: absolute;
            top: 580px;
            left: 60px;
            width: calc(50% - 60px);
            text-align: left;
        }
        .instructor-section {
            position: absolute;
            top: 580px;
            right: 60px;
            width: calc(50% - 60px);
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 3px;
        }
        .instructor-section p {
            margin: 0;
            position: relative;
            z-index: 10;
        }
        .instructor-section .signature-container {
            width: 100%;
        }
        .instructor-section .signature-img {
            position: relative;
            z-index: 1;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .footer {
            font-size: 10px;
            text-align: center;
            position: absolute;
            bottom: 30px;
            left: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <h1 class="certificate-title">ZAŚWIADCZENIE O UKOŃCZENIU SZKOLENIA</h1>
    <p>Pan/i</p>
    <h2>{{ $participant->first_name }} {{ $participant->last_name }}</h2>

    @if (!empty($participant->birth_date) && !empty($participant->birth_place))
        <p>urodzony/a: {{ \Carbon\Carbon::parse($participant->birth_date)->format('d.m.Y') }}r. w miejscowości {{ $participant->birth_place }}</p>
    @else
        <p>&nbsp;</p>
    @endif

    <p>ukończył/a szkolenie</p>
    <p>zorganizowane w dniu {{ \Carbon\Carbon::parse($course->start_date)->format('d.m.Y') }}r. w wymiarze {{ $durationMinutes }} minut, przez</p>

    <p class="bold">Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji</p>

    <h3>TEMAT SZKOLENIA</h3>
    <h2 class="course-title">{{ $course->title }}</h2>

    @php
        $description = trim($course->description ?? '');
        if (!empty($description)) {
            // Dynamiczne dostosowanie rozmiaru czcionki na podstawie długości zakresu
            $itemCount = 0;
            if (preg_match('/^\\d+\\.\\s*/m', $description)) {
                $itemCount = preg_match_all('/^\\d+\\.\\s*/m', $description);
            }
            // Ustaw rozmiar czcionki na podstawie liczby punktów
            $fontSize = $itemCount > 4 ? '13px' : '16px';
            $marginBottom = $itemCount > 4 ? '2px' : '5px';
            if (preg_match('/^\\d+\\.\\s*/m', $description)) {
                // To jest lista numerowana - formatuj jako <ol> z dynamiczną czcionką
                $items = preg_split('/\\n(?=\\d+\\.)/', $description);
                echo '<ol style="text-align: left; margin-left: 45px; margin-right: 45px; font-size: ' . $fontSize . ';">';
                foreach ($items as $item) {
                    $cleanItem = preg_replace('/^\\d+\\.\\s*/', '', trim($item));
                    if ($cleanItem) {
                        echo '<li style="text-align: left; margin-bottom: ' . $marginBottom . ';">' . htmlspecialchars($cleanItem) . '</li>';
                    }
                }
                echo '</ol>';
            } else {
                // To jest zwykły tekst - jako akapit wyrównany do lewej z dynamiczną czcionką
                echo '<p style="text-align: left; margin-left: 45px; margin-right: 45px; font-size: ' . $fontSize . ';">' . htmlspecialchars($description) . '</p>';
            }
        }
    @endphp

    <div class="date-section">
        <p style="margin: 0;">Data, {{ \Carbon\Carbon::parse($course->end_date)->format('d.m.Y') }}r.<br>
        Nr rejestru: {{ $certificateNumber }}</p>
    </div>

    <div class="instructor-section">
        <p>
            @php
                // Określanie tytułu na podstawie płci
                $title = match($instructor->gender ?? 'prefer_not_to_say') {
                    'male' => 'prowadzący:',
                    'female' => 'prowadząca:',
                    'other' => 'trener:',
                    default => 'prowadzący/a:'
                };
            @endphp
            {{ $title }}<br>
            <span class="bold">{{ $instructor->first_name }} {{ $instructor->last_name }}</span>
        </p>
        
        <div class="signature-container">
            @if(!empty($instructor->signature))
                @php
                    // Obsługa ścieżki do grafiki podpisu
                    if ($isPdfMode ?? false) {
                        // Dla PDF używamy base64 encoding - najpewniejsze rozwiązanie
                        $signatureFile = storage_path('app/public/' . $instructor->signature);
                        if (file_exists($signatureFile)) {
                            $signatureSrc = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($signatureFile));
                        } else {
                            $signatureSrc = null;
                        }
                    } else {
                        // Dla HTML używamy asset()
                        $signatureSrc = asset('storage/' . $instructor->signature);
                    }
                @endphp
                @if($signatureSrc)
                    <img src="{{ $signatureSrc }}" alt="Podpis" class="signature-img" style="max-width: 200px; max-height: 80px; width: auto; height: auto;">
                @endif
            @endif
        </div>
    </div>

    <div class="footer">
        Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji<br>ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>- AKREDYTACJA MAZOWIECKIEGO KURATORA OŚWIATY -
    </div>
</body>
</html>
