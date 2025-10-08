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
        .certificate-title {
            font-size: 42px;
            font-weight: bold;
            color: #2c3e50;
        }
        .course-title {
            word-break: keep-all;
            font-size: 28px;
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
    <h1 class="certificate-title">CERTYFIKAT UKOŃCZENIA</h1>
    <p>ukończył/a szkolenie</p>
    <p>zorganizowane w dniu {{ \Carbon\Carbon::parse($course->start_date)->format('d.m.Y') }}r. przez</p>

    <p class="bold">Platforma Nowoczesnej Edukacji</p>

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
